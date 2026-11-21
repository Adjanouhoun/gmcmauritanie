<?php
namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class AdminTrainingController extends AbstractController
{
    #[Route('/admin/formations',name:'admin_trainings',methods:['GET'])]
    public function index(): Response { return $this->redirectToRoute('cms_list',['type'=>'training']); }
    #[Route('/admin/formations/{id}',requirements:['id'=>'\d+'],methods:['GET'])]
    public function legacy(): Response { return $this->redirectToRoute('cms_list',['type'=>'training']); }
}
