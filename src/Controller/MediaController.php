<?php
namespace App\Controller;
use App\Entity\Media;
use App\Cms\ImageProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{Request,Response,JsonResponse,BinaryFileResponse,ResponseHeaderBag};
use Symfony\Component\HttpFoundation\File\{UploadedFile,Exception\FileException};
use Symfony\Component\Routing\Attribute\Route;

final class MediaController extends AbstractController
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $root, private ImageProcessor $images) {}
    private function iniBytes(string $key): int
    {
        $value=trim((string)ini_get($key));
        return (int)$value * match(strtolower(substr($value,-1))){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};
    }
    private function uploadLimit(): int
    {
        $limits=[12*1024*1024];
        if (($limit=$this->iniBytes('upload_max_filesize'))>0) $limits[]=$limit;
        if (($limit=$this->iniBytes('post_max_size'))>0) $limits[]=max(1,$limit-65536);
        return min($limits);
    }
    private function metadata(Media $media): array
    {
        return $this->images->info($media)+['thumbnail'=>$this->images->supported($media)?'/media/'.$media->getId().'/variant/thumb':($media->uploaded?'/media/'.$media->getId():'/'.$media->path),'id'=>$media->getId(),'name'=>$media->name,'alt'=>$media->alt,'kind'=>$media->isImage()?'image':'document','url'=>$media->uploaded?'/media/'.$media->getId():'/'.$media->path];
    }
    #[Route('/admin/medias/catalogue',name:'cms_media_catalogue',methods:['GET'])]
    public function catalogue(EntityManagerInterface $em): JsonResponse
    {
        $rows=$em->getRepository(Media::class)->findBy(['archived'=>false],['id'=>'DESC']);
        $response=$this->json(['items'=>array_map($this->metadata(...),$rows),'maxBytes'=>$this->uploadLimit()]);
        $response->headers->set('Cache-Control','private, no-store');return $response;
    }
    #[Route('/admin/medias',name:'cms_media',methods:['GET','POST'])]
    public function index(Request $request,EntityManagerInterface $em): Response
    {
        $error=null;$status=422;$limit=$this->uploadLimit();$json=str_contains($request->headers->get('Accept',''),'application/json');
        if ($request->isMethod('POST')) {
            $postLimit=$this->iniBytes('post_max_size');
            if ($postLimit>0 && (int)$request->server->get('CONTENT_LENGTH',0)>$postLimit) {
                // PHP discards both the file and CSRF field when post_max_size is exceeded.
                $error='Le fichier dépasse la limite autorisée. Choisissez un fichier plus petit.';
            } elseif (!$this->isCsrfTokenValid('media',$request->request->get('_token'))) {
                if ($json) return $this->json(['error'=>'La session a expiré. Enregistrez votre contenu puis rechargez la page.'],403);
                throw $this->createAccessDeniedException();
            } else {
                $file=$request->files->get('file');$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
                if (!$file instanceof UploadedFile || !$file->isValid() || $file->getSize()>$limit) $error='Choisissez une image JPG, PNG, WebP ou un PDF respectant la limite affichée.';
                elseif (!isset($types[$file->getMimeType()])) $error='Format non autorisé. Utilisez JPG, PNG, WebP ou PDF.';
                elseif ($file->getMimeType()!=='application/pdf' && (!(($size=@getimagesize($file->getPathname()))) || $size[0]*$size[1]>30000000)) $error='Image invalide ou trop grande (30 millions de pixels maximum).';
                else {
                    $media=new Media();$media->name=mb_substr(basename($file->getClientOriginalName()),0,255);$media->mime=$file->getMimeType();$media->uploaded=true;
                    $alt=$request->request->get('alt','');$media->alt=is_string($alt)?mb_substr(trim($alt),0,300):'';
                    $media->path=bin2hex(random_bytes(20)).'.'.$types[$media->mime];
                    try { $file->move($this->root.'/var/media',$media->path); }
                    catch (FileException) { $error='Le fichier n’a pas pu être enregistré. Réessayez plus tard.';$status=503; }
                    if (!$error) {
                        try { $em->persist($media);$em->flush(); }
                        catch (\Throwable $failure) { @unlink($this->root.'/var/media/'.$media->path);throw $failure; }
                        if ($json) return $this->json(['item'=>$this->metadata($media)],201);
                        $this->addFlash('success','Média ajouté à la bibliothèque.');return $this->redirectToRoute('cms_media');
                    }
                }
            }
        }
        if ($json && $error) return $this->json(['error'=>$error],$status);
        return $this->render('admin/media.html.twig',['media'=>$em->getRepository(Media::class)->findBy([],['id'=>'DESC']),'error'=>$error,'maxMb'=>floor($limit/104857.6)/10],new Response(status:$error?$status:200));
    }
    #[Route('/admin/medias/{id}',name:'cms_media_edit',requirements:['id'=>'\d+'],methods:['POST'])]
    public function edit(int $id,Request $request,EntityManagerInterface $em): Response
    {
        $media=$em->find(Media::class,$id);if (!$media) throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('media_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException();
        $alt=$request->request->get('alt','');if (!is_string($alt)||mb_strlen($alt)>300) return new Response('Description trop longue.',422);
        $media->alt=trim($alt);$media->archived=$request->request->get('archived')==='1';$em->flush();
        $this->addFlash('success','Média mis à jour. Les contenus qui l’utilisent conservent leur image.');return $this->redirectToRoute('cms_media');
    }
    #[Route('/media/{id}/variant/{profile}',name:'cms_media_variant',requirements:['id'=>'\d+'],methods:['GET'])]
    public function variant(int $id,string $profile,Request $request,EntityManagerInterface $em): Response
    {
        $media=$em->find(Media::class,$id);
        if (!$media || !isset(ImageProcessor::PROFILES[$profile]) || !$this->images->supported($media)) throw $this->createNotFoundException();
        $path=$this->images->variant($media,$profile);
        if (!$path) {
            if (!$this->images->path($media)) throw $this->createNotFoundException();
            return $this->redirect($media->uploaded?'/media/'.$id:'/'.$media->path);
        }
        $response=new BinaryFileResponse($path);$response->headers->set('Content-Type','image/webp');$response->headers->set('X-Content-Type-Options','nosniff');
        $response->setPublic()->setMaxAge(3600);$response->setAutoEtag();$response->isNotModified($request);return $response;
    }
    #[Route('/media/{id}',name:'cms_media_file',requirements:['id'=>'\d+'],methods:['GET'])]
    public function download(int $id,EntityManagerInterface $em): Response
    {
        $media=$em->find(Media::class,$id);if (!$media || !$media->uploaded || !preg_match('/^[a-f0-9]{40}\.(jpg|png|webp|pdf)$/',$media->path)) throw $this->createNotFoundException();
        $path=$this->root.'/var/media/'.$media->path;if (!is_file($path)) throw $this->createNotFoundException();
        $response=new BinaryFileResponse($path);$response->headers->set('Content-Type',$media->mime);$response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('Content-Security-Policy',"default-src 'none'; sandbox");
        if ($media->mime==='application/pdf') $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT,'programme.pdf');return $response;
    }
}
