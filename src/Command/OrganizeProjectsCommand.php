<?php
namespace App\Command;
use App\Entity\CmsEntry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
#[AsCommand(name:'app:cms:organize-projects',description:'Rattache les anciennes réalisations à des catégories administrables, sans remplacer leurs contenus.')]
final class OrganizeProjectsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em) { parent::__construct(); }
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        $repo=$this->em->getRepository(CmsEntry::class);$categories=[];$changed=0;
        foreach($repo->findBy(['type'=>'project_category']) as $category)$categories[$category->slug]=$category;
        $this->em->wrapInTransaction(function() use($repo,&$categories,&$changed) {
            foreach($repo->findBy(['type'=>'project']) as $project) {
                if(!empty($project->data['categoryId']) || empty($project->data['category']))continue;
                $label=trim($project->data['category']);$slug=(new AsciiSlugger())->slug($label)->lower()->toString();
                $label=match($slug){'construction-metallique'=>'Construction métallique','tuyauterie'=>'Tuyauterie industrielle','maintenance'=>'Maintenance industrielle','genic-civil-genie-civil','genie-civil'=>'Génie civil',default=>$label};
                $slug=(new AsciiSlugger())->slug($label)->lower()->toString();
                if(!isset($categories[$slug])){$c=new CmsEntry('project_category',$slug);$c->title=$label;$c->status='published';$this->em->persist($c);$categories[$slug]=$c;$this->em->flush();}
                $project->data['categoryId']=$categories[$slug]->getId();++$changed;
            }
        });
        $output->writeln($changed.' réalisation(s) rattachée(s).');return Command::SUCCESS;
    }
}
