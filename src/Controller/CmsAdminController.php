<?php
namespace App\Controller;
use App\Cms\{Schema,Html,Content,ImageProcessor};
use App\Entity\{CmsEntry,Media};
use Doctrine\ORM\{EntityManagerInterface,OptimisticLockException};
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class CmsAdminController extends AbstractController
{
    #[Route('/admin/contenus/{type}', name:'cms_list', methods:['GET'])]
    public function index(string $type,Content $cms): Response
    {
        if (!isset(Schema::TYPES[$type])) throw $this->createNotFoundException();
        return $this->render('admin/cms_list.html.twig',['type'=>$type,'types'=>Schema::TYPES,'rows'=>$cms->entries($type,true),'pageSettings'=>isset(Schema::PAGE_SECTIONS[$type])?$cms->one('page',Schema::PAGE_SECTIONS[$type]):null,'single'=>in_array($type,Schema::SINGLE) || $type==='page']);
    }
    #[Route('/admin/contenus/{section}/presentation',name:'cms_page_settings',methods:['GET','POST'])]
    public function presentation(string $section,Request $request,EntityManagerInterface $em,ImageProcessor $images): Response
    {
        if (!isset(Schema::PAGE_SECTIONS[$section])) throw $this->createNotFoundException();
        $page=$em->getRepository(CmsEntry::class)->findOneBy(['type'=>'page','slug'=>Schema::PAGE_SECTIONS[$section]]);
        if (!$page) throw $this->createNotFoundException();
        return $this->edit('page',$request,$em,$images,$page->getId());
    }
    #[Route('/admin/contenus/{type}/nouveau', name:'cms_new', methods:['GET','POST'])]
    #[Route('/admin/contenus/{type}/{id}/modifier', name:'cms_edit', requirements:['id'=>'\d+'], methods:['GET','POST'])]
    public function edit(string $type,Request $request,EntityManagerInterface $em,ImageProcessor $images,?int $id=null): Response
    {
        if (!isset(Schema::TYPES[$type])) throw $this->createNotFoundException();
        $fixed=in_array($type,Schema::SINGLE) || $type==='page';
        if (!$id && $fixed) throw $this->createNotFoundException();
        $entry=$id ? $em->find(CmsEntry::class,$id) : new CmsEntry($type,'');
        if (!$entry || $entry->type!==$type) throw $this->createNotFoundException();
        $pageSection=$type==='page'?array_search($entry->slug,Schema::PAGE_SECTIONS,true):false;
        $values=['title'=>$entry->title,'slug'=>$entry->slug,'status'=>$entry->status,'position'=>$entry->position,'version'=>$entry->getVersion(),'data'=>$entry->data];
        $cms=new Content($em,$images);
        if ($type==='menu' && Content::externalUrl($values['data']['link']??'')) { $values['data']['externalUrl']=$values['data']['link'];$values['data']['link']='external'; }
        $errors=[];$status=200;$fields=Schema::fields($type);
        if ($request->isMethod('POST')) {
            $post=$request->request->all();
            if (!is_string($post['_token']??null) || !$this->isCsrfTokenValid('cms_'.($id??'new_'.$type),$post['_token'])) throw $this->createAccessDeniedException();
            foreach (['title','slug','status'] as $key) $values[$key]=is_string($post[$key]??null)?trim($post[$key]):'';
            $position=filter_var($post['position']??null,FILTER_VALIDATE_INT);
            if ($position===false || $position<0 || $position>9999) $errors[]='L’ordre doit être compris entre 0 et 9999.';
            $values['position']=$position?:0;
            if (!$values['title'] || mb_strlen($values['title'])>200) $errors[]='Le titre est obligatoire (200 caractères maximum).';
            if (!$values['slug']) $values['slug']=(new AsciiSlugger())->slug($values['title'])->lower()->toString();
            // Existing addresses remain stable to preserve inbound links.
            if ($id) $values['slug']=$entry->slug;
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$values['slug']) || strlen($values['slug'])>160) $errors[]='Adresse invalide : utilisez des lettres minuscules, chiffres et tirets.';
            if (!in_array($values['status'],$fixed?['published']:['draft','published','archived'],true)) $errors[]='État de publication invalide.';
            $data=is_array($post['data']??null)?$post['data']:[];$values['data']=[];
            foreach ($fields as $key=>[$label,$widget,$required]) {
                $v=$data[$key]??'';
                if ($widget==='category') {
                    $category=is_string($v)&&ctype_digit($v)?$em->find(CmsEntry::class,(int)$v):null;
                    if ($v!=='' && (!$category || $category->type!=='project_category')) $errors[]='Choisissez une activité valide.';
                    $values['data'][$key]=$category?->getId();continue;
                }
                if ($widget==='year') {
                    if ($v!=='' && (!is_string($v) || !preg_match('/^(19|20|21)[0-9]{2}$/',$v))) $errors[]='L’année doit être comprise entre 1900 et 2199.';
                    $values['data'][$key]=is_string($v)?$v:'';continue;
                }
                if ($widget==='range') {
                    $number=$v===''?50:filter_var($v,FILTER_VALIDATE_INT);
                    if ($number===false || $number<0 || $number>100) $errors[]=$label.' : choisissez une valeur entre 0 et 100.';
                    $values['data'][$key]=$number===false?50:max(0,min(100,$number));continue;
                }
                if ($widget==='checkbox') { $values['data'][$key]=$v==='1';continue; }
                if ($widget==='gallery') {
                    if (!is_array($v) && $v!=='') $errors[]=$label.' : sélection invalide.';
                    $ids=[];
                    foreach (is_array($v)?$v:[] as $mediaId) {
                        if (!is_string($mediaId) || !ctype_digit($mediaId) || (int)$mediaId<1) { $errors[]=$label.' : image invalide.';continue; }
                        $media=$em->find(Media::class,(int)$mediaId);
                        if (!$media || !$media->isImage()) { $errors[]=$label.' : image invalide.';continue; }
                        $ids[]=$media->getId();
                    }
                    $ids=array_values(array_unique($ids));
                    if (count($ids)>30) $errors[]='Une galerie est limitée à 30 images.';
                    $values['data'][$key]=$ids;continue;
                }
                if (!is_string($v)) { $errors[]=$label.' : valeur invalide.';$v=''; }
                $v=trim($v);
                if (in_array($widget,['image','document'])) {
                    $media=$v!==''&&ctype_digit($v)?$em->find(Media::class,(int)$v):null;
                    if (($required && !$media) || ($v!=='' && (!$media || ($widget==='image'?!$media->isImage():$media->mime!=='application/pdf')))) $errors[]=$label.' : choisissez un média valide.';
                    $values['data'][$key]=$media?->getId();continue;
                }
                $max=$widget==='rich'?60000:($widget==='textarea'?5000:500);
                if (mb_strlen($v)>$max) $errors[]=$label.' : texte trop long.';
                if ($widget==='rich') $v=Html::sanitize($v);
                if ($required && trim(strip_tags($v))==='') $errors[]=$label.' est obligatoire.';
                if ($widget==='url' && !Html::safeUrl($v)) $errors[]=$label.' : indiquez un lien interne ou une adresse https.';
                if ($widget==='email' && $v!=='' && !filter_var($v,FILTER_VALIDATE_EMAIL)) $errors[]='Adresse e-mail invalide.';
                if ($widget==='phone' && $v!=='' && (!preg_match('/^[+0-9 () .-]{5,40}$/',$v) || !preg_match('/^\+?[0-9]{5,20}$/',str_replace([' ', '(', ')', '.', '-'],'',$v)))) $errors[]=$label.' : numéro invalide.';
                $values['data'][$key]=$v;
            }
            if ($type==='menu') {
                $link=$values['data']['link'];
                if ($link==='external') {
                    $link=$values['data']['externalUrl'];
                    if (!Content::externalUrl($link)) $errors[]='Indiquez une adresse externe complète en https ou http.';
                } elseif (!isset($cms->destinations()[$link]) && !($id && $link===($entry->data['link']??'') && isset($cms->destinations(true)[$link]))) {
                    $errors[]='Choisissez une page publiée dans la liste.';
                }
                if (!$errors) { $values['data']['link']=$link;unset($values['data']['externalUrl']); }
            }
            $version=filter_var($post['version']??null,FILTER_VALIDATE_INT);
            $values['version']=$version?:0;
            if ($id && $version!==$entry->getVersion()) { $errors[]='Une autre session a modifié ce contenu. Rechargez la page.';$status=409; }
            $existing=$em->getRepository(CmsEntry::class)->findOneBy(['type'=>$type,'slug'=>$values['slug']]);
            if ($existing && $existing->getId()!==$id) $errors[]='Cette adresse est déjà utilisée.';
            if (!$errors) {
                foreach (['title','slug','status','position','data'] as $key) $entry->$key=$values[$key];
                // Only one published project is featured; drafts never displace the public selection.
                if ($type==='project' && $entry->status==='published' && ($entry->data['featured']??false)) {
                    foreach ($em->getRepository(CmsEntry::class)->findBy(['type'=>'project','status'=>'published']) as $other) {
                        if ($other!==$entry && ($other->data['featured']??false)) $other->data['featured']=false;
                    }
                }
                $em->persist($entry);
                try { $em->flush(); }
                catch (OptimisticLockException|UniqueConstraintViolationException) { return new Response('Le contenu ou son adresse a changé. Rechargez la page.',409); }
                if ($type==='slide') foreach (['image'=>'slide','mobile'=>'slide_mobile'] as $key=>$usage) {
                    $media=isset($entry->data[$key])?$em->find(Media::class,$entry->data[$key]):null;
                    if ($media && ($warning=$images->warning($media,$usage))) $this->addFlash('warning',$warning);
                }
                $this->addFlash('success','Contenu enregistré.');if ($pageSection!==false) return $this->redirectToRoute('cms_page_settings',['section'=>$pageSection]);return $this->redirectToRoute('cms_edit',['type'=>$type,'id'=>$entry->getId()]);
            }
            if ($status===200) $status=422;
        }
        return $this->render('admin/cms_edit.html.twig',['pageSection'=>$pageSection,'navType'=>$pageSection!==false?$pageSection:$type,'type'=>$type,'types'=>Schema::TYPES,'entry'=>$entry,'values'=>$values,'fields'=>$fields,'errors'=>$errors,'fixed'=>$fixed,'categories'=>$em->getRepository(CmsEntry::class)->findBy(['type'=>'project_category'],['position'=>'ASC','title'=>'ASC']),'media'=>$em->getRepository(Media::class)->findBy([],['id'=>'DESC'])],new Response(status:$status));
    }
    #[Route('/admin/apercu/{id}',name:'cms_preview',requirements:['id'=>'\d+'],methods:['GET'])]
    public function preview(int $id,EntityManagerInterface $em,Content $cms,CmsSiteController $site): Response
    {
        $entry=$em->find(CmsEntry::class,$id);if (!$entry) throw $this->createNotFoundException();
        $cms->preview=$entry;
        try {
        $response=match($entry->type){
            'custom_page'=>$site->customPage($entry->slug,$cms),
            'service','training','project'=>$site->detail($entry->type,$entry->slug,$cms),
            'about','about_block'=>$site->page('a-propos',$cms),
            'contact'=>$site->page('contact',$cms),
            'project_category'=>$site->page('realisations',$cms),
            'page'=>$site->page($entry->slug,$cms),
            default=>$site->page('accueil',$cms),
        };
        } finally { $cms->preview=null; }
        $response->headers->set('Cache-Control','private, no-store');$response->headers->set('X-Robots-Tag','noindex, nofollow');return $response;
    }
}
