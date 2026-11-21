<?php
namespace App\Controller;
use App\Cms\Content;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class CmsSiteController extends AbstractController
{
    #[Route('/',name:'app_home',defaults:['page'=>'accueil'],methods:['GET'])]
    #[Route('/index.html',name:'app_home_legacy',defaults:['page'=>'accueil'],methods:['GET'])]
    #[Route('/services',name:'app_services',defaults:['page'=>'services'],methods:['GET'])]
    #[Route('/services.html',name:'app_services_legacy',defaults:['page'=>'services'],methods:['GET'])]
    #[Route('/formation',name:'app_formation',defaults:['page'=>'formation'],methods:['GET'])]
    #[Route('/formation.html',name:'app_formation_legacy',defaults:['page'=>'formation'],methods:['GET'])]
    #[Route('/a-propos',name:'app_a_propos',defaults:['page'=>'a-propos'],methods:['GET'])]
    #[Route('/a-propos.html',name:'app_a_propos_legacy',defaults:['page'=>'a-propos'],methods:['GET'])]
    #[Route('/contact',name:'app_contact',defaults:['page'=>'contact'],methods:['GET'])]
    #[Route('/contact.html',name:'app_contact_legacy',defaults:['page'=>'contact'],methods:['GET'])]
    #[Route('/realisations',name:'app_realisations',defaults:['page'=>'realisations'],methods:['GET'])]
    #[Route('/realisations.html',name:'app_realisations_legacy',defaults:['page'=>'realisations'],methods:['GET'])]
    public function page(string $page,Content $cms): Response
    {
        if (!in_array($page,['accueil','services','formation','a-propos','contact','realisations'])) throw $this->createNotFoundException();
        return $this->render('cms/'.$page.'.html.twig',['page'=>$page,'meta'=>$cms->one('page',$page)]);
    }
    #[Route('/services/{slug}',name:'cms_service_detail',defaults:['type'=>'service'],methods:['GET'])]
    #[Route('/formations/{slug}',name:'cms_training_detail',defaults:['type'=>'training'],methods:['GET'])]
    #[Route('/realisations/{slug}',name:'cms_project_detail',defaults:['type'=>'project'],methods:['GET'])]
    public function detail(string $type,string $slug,Content $cms): Response
    {
        $entry=$cms->one($type,$slug);if (!$entry) throw $this->createNotFoundException();
        return $this->render('cms/detail.html.twig',['page'=>$type==='training'?'formation':($type==='service'?'services':'realisations'),'meta'=>$entry,'entry'=>$entry]);
    }
    #[Route('/service-{slug}',name:'cms_service_legacy',requirements:['slug'=>'[a-z0-9-]+'],methods:['GET'])]
    #[Route('/service-{slug}.html',name:'cms_service_html_legacy',requirements:['slug'=>'[a-z0-9-]+'],methods:['GET'])]
    public function legacy(string $slug,Content $cms): Response
    {
        if (!$cms->one('service',$slug)) throw $this->createNotFoundException();
        return $this->redirectToRoute('cms_service_detail',['slug'=>$slug],301);
    }
}
