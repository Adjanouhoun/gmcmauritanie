<?php
namespace App\Cms;
use App\Entity\CmsEntry;
use App\Entity\Media;
use Doctrine\ORM\EntityManagerInterface;
final class Content
{
    public ?CmsEntry $preview = null;
    public function __construct(private EntityManagerInterface $em, private ImageProcessor $images) {}
    public function entries(string $type, bool $all=false): array
    {
        $rows=$this->em->getRepository(CmsEntry::class)->findBy($all ? ['type'=>$type] : ['type'=>$type,'status'=>'published'],['position'=>'ASC','id'=>'ASC']);
        if (!$all && $this->preview?->type===$type) {
            $rows=array_values(array_filter($rows,fn($r)=>$r->getId()!==$this->preview->getId()));$rows[]=$this->preview;
            usort($rows,fn($a,$b)=>[$a->position,$a->getId()]<=>[$b->position,$b->getId()]);
        }
        return $rows;
    }
    public function one(string $type,string $slug='main'): ?CmsEntry
    {
        if ($this->preview?->type===$type && $this->preview->slug===$slug) return $this->preview;
        return $this->em->getRepository(CmsEntry::class)->findOneBy(['type'=>$type,'slug'=>$slug,'status'=>'published']);
    }
    public function destinations(bool $all=false): array
    {
        $choices=['/'=>'Accueil','/services'=>'Services','/formation'=>'Formations','/a-propos'=>'À propos','/realisations'=>'Réalisations','/contact'=>'Contact'];
        foreach (['custom_page'=>'/pages/','service'=>'/services/','training'=>'/formations/','project'=>'/realisations/'] as $type=>$prefix) {
            foreach ($this->entries($type,$all) as $entry) $choices[$prefix.$entry->slug]=Schema::TYPES[$type].' — '.$entry->title.($entry->status!=='published'?' (non publié)':'');
        }
        return $choices;
    }
    public function menuEntries(string $placement): array
    {
        $destinations=$this->destinations();
        return array_values(array_filter($this->entries('menu'),static function($item) use ($placement,$destinations) {
            $link=$item->data['link']??'';
            $visible=$item->data[$placement]??($placement==='header');
            return $visible && (isset($destinations[$link]) || self::externalUrl($link));
        }));
    }
    public static function externalUrl(string $url): bool
    {
        return (bool) filter_var($url,FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url,PHP_URL_SCHEME)??''),['http','https'],true);
    }
    public function media(mixed $id): ?Media { return is_numeric($id) ? $this->em->find(Media::class,(int)$id) : null; }
    public function mediaUrl(mixed $id, string $profile='photo'): string
    {
        $media=$this->media($id);if ($media && $profile!=='original' && isset(ImageProcessor::PROFILES[$profile]) && $this->images->supported($media)) return '/media/'.$media->getId().'/variant/'.$profile;return !$media ? '' : ($media->uploaded ? '/media/'.$media->getId() : '/'.$media->path);
    }
    public function imageInfo(mixed $id): array { $m=$this->media($id);return $m?$this->images->info($m):[]; }
    public function imageWarning(mixed $id,string $usage): ?string { $m=$this->media($id);return $m?$this->images->warning($m,$usage):null; }
    public function projectCategory(CmsEntry $project): ?CmsEntry
    {
        $id=$project->data['categoryId']??null;
        $category=is_numeric($id)?$this->em->find(CmsEntry::class,(int)$id):null;
        return $category?->type==='project_category' && $category->status==='published'?$category:null;
    }
    public function alt(mixed $id): string { return $this->media($id)?->alt ?? ''; }
}
