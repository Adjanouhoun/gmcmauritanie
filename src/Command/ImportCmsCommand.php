<?php
namespace App\Command;
use App\Cms\Html;
use App\Entity\{CmsEntry,Media,Homepage,ServiceOffering,Training};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
#[AsCommand(name:'app:cms:import',description:'Importer les contenus existants sans écraser les contenus du CMS.')]
final class ImportCmsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em,#[Autowire('%kernel.project_dir%')] private string $root) {parent::__construct();}
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        $seed=json_decode(file_get_contents($this->root.'/config/cms_seed.json'),true,flags:JSON_THROW_ON_ERROR);$mediaMap=[];$count=0;
        $this->em->beginTransaction();
        try {
            foreach($seed['media'] as $path) {
                $media=$this->em->getRepository(Media::class)->findOneBy(['path'=>$path,'uploaded'=>false]);
                if (!$media) {
                    if (!is_file($this->root.'/public/'.$path)) continue;
                    $media=new Media();$media->path=$path;$media->name=basename($path);$media->alt=pathinfo($path,PATHINFO_FILENAME);
                    $media->mime=str_ends_with($path,'.svg')?'image/svg+xml':((new \finfo(FILEINFO_MIME_TYPE))->file($this->root.'/public/'.$path)?:'image/jpeg');
                    $this->em->persist($media);
                }
                $mediaMap[$path]=$media;
            }
            $this->em->flush();
            $resolve=function($value) use (&$resolve,$mediaMap) {
                if(is_array($value)) return array_map($resolve,$value);
                if(is_string($value)&&str_starts_with($value,'@img/'))return ($mediaMap[substr($value,1)]??null)?->getId();
                return $value;
            };
            foreach($seed['entries'] as $row) {
                if($this->em->getRepository(CmsEntry::class)->findOneBy(['type'=>$row['type'],'slug'=>$row['slug']]))continue;
                $entry=new CmsEntry($row['type'],$row['slug']);$entry->title=$row['title'];$entry->data=$resolve($row['data']);$entry->position=$row['position'];$entry->status='published';
                if($entry->type==='home' && ($old=$this->em->find(Homepage::class,1))) {$entry->title=$old->title;$entry->data['intro']='<p>'.htmlspecialchars($old->presentation,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>';}
                if($entry->type==='service' && ($old=$this->em->getRepository(ServiceOffering::class)->findOneBy(['slug'=>$entry->slug]))) {$entry->title=$old->title;$entry->data['summary']=$old->summary;$entry->data['tagline']=$old->tagline;if(!$entry->data['body'])$entry->data['body']='<p>'.htmlspecialchars($old->description,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>';}
                if($entry->type==='training' && ($old=$this->em->getRepository(Training::class)->findOneBy(['slug'=>$entry->slug]))) {
                    $entry->title=$old->title;foreach(['shortTitle','summary','objectives','audience','duration','note'] as $key)$entry->data[$key]=$old->$key;
                    $entry->data['body']='<p>'.htmlspecialchars($old->description,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>';$entry->data['featured']=$old->isFeatured();
                }
                foreach(['body','intro','secondary'] as $key) if(isset($entry->data[$key]))$entry->data[$key]=Html::sanitize($entry->data[$key]);
                $this->em->persist($entry);++$count;
            }
            $this->em->flush();$this->em->commit();
        } catch(\Throwable $error) {$this->em->rollback();throw $error;}
        $output->writeln($count.' contenus importés. Les contenus existants du CMS ont été conservés.');return Command::SUCCESS;
    }
}
