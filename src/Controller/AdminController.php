<?php
namespace App\Controller;
use App\Cms\{Schema,Content};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
final class AdminController extends AbstractController
{
    #[Route('/admin/connexion',name:'admin_login',methods:['GET','POST'])]
    public function login(AuthenticationUtils $auth): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) return $this->redirectToRoute('admin_home');
        return $this->render('admin/login.html.twig',['last_username'=>$auth->getLastUsername(),'error'=>$auth->getLastAuthenticationError()]);
    }
    #[Route('/admin/deconnexion',name:'admin_logout',methods:['POST'])]
    public function logout(): never { throw new \LogicException('Handled by the firewall.'); }
    #[Route('/admin',name:'admin_home',methods:['GET'])]
    public function home(Content $cms): Response
    {
        $counts=[];foreach(Schema::TYPES as $type=>$label) $counts[$type]=count($cms->entries($type,true));
        return $this->render('admin/dashboard.html.twig',['types'=>Schema::TYPES,'counts'=>$counts]);
    }
}
