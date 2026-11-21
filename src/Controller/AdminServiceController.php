<?php
namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class AdminServiceController extends AbstractController
{
    #[Route('/admin/services',name:'admin_services',methods:['GET'])]
    public function index(): Response { return $this->redirectToRoute('cms_list',['type'=>'service']); }
    #[Route('/admin/services/{id}',requirements:['id'=>'\d+'],methods:['GET'])]
    public function legacy(): Response { return $this->redirectToRoute('cms_list',['type'=>'service']); }
}
