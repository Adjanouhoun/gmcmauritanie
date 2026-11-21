<?php
namespace App\Cms;
use App\Entity\Media;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Originals are never modified. Only bounded, allowlisted variants are generated. */
final class ImageProcessor
{
    public const PROFILES=['logo'=>[480,320],'icon'=>[256,256],'thumb'=>[320,240],'photo'=>[1920,1920],'photo_small'=>[768,768],'slide'=>[1920,1920],'slide_mobile'=>[960,1440]];
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $root) {}
    public function path(Media $media): ?string
    {
        $base=realpath($this->root.($media->uploaded?'/var/media':'/public'));
        $path=$base?realpath($base.'/'.$media->path):false;
        return $path && str_starts_with($path,$base.DIRECTORY_SEPARATOR) && is_file($path)?$path:null;
    }
    public function info(Media $media): array
    {
        $path=$this->path($media);$size=$path?@getimagesize($path):false;
        $orientation=$path && $media->mime==='image/jpeg' && function_exists('exif_read_data')?(int)(@exif_read_data($path)['Orientation']??1):1;
        return ['width'=>$size?($orientation>=5&&$orientation<=8?$size[1]:$size[0]):null,'height'=>$size?($orientation>=5&&$orientation<=8?$size[0]:$size[1]):null,'orientation'=>$orientation,'vector'=>$media->mime==='image/svg+xml'];
    }
    public function supported(Media $media): bool { return in_array($media->mime,['image/jpeg','image/png','image/webp'],true); }
    public function warning(Media $media,string $usage): ?string
    {
        $i=$this->info($media);
        if ($i['vector'] || !$i['width']) return null;
        [$w,$h]=match($usage){'slide'=>[1920,700],'slide_mobile'=>[780,940],'icon'=>[128,128],'logo'=>[220,160],default=>[768,400]};
        return $i['width']<$w || $i['height']<$h ? "Image de {$i['width']} × {$i['height']} px : résolution faible pour cet usage. Recommandation : au moins $w × $h px. L’image reste utilisable, mais peut paraître floue." : null;
    }
    public function variant(Media $media,string $profile): ?string
    {
        if (!function_exists('imagewebp') || !function_exists('imagecreatefromwebp')) return null;
        if (!isset(self::PROFILES[$profile]) || !$this->supported($media) || !($source=$this->path($media))) return null;
        $info=$this->info($media);if (!$info['width']) return null;
        $dir=$this->root.'/var/media/variants';
        $key=hash('sha256','v1|'.$source.'|'.filemtime($source).'|'.filesize($source).'|'.$profile);
        $path=$dir.'/'.$key.'.webp';if(is_file($path))return $path;
        [$maxW,$maxH]=self::PROFILES[$profile];
        // Decoding and EXIF rotation can temporarily require several full-size buffers.
        $limit=trim((string)ini_get('memory_limit'));$bytes=(int)$limit*match(strtolower(substr($limit,-1))){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};
        $estimate=$info['width']*$info['height']*12+$maxW*$maxH*8+16*1024*1024;
        if ($bytes>0 && memory_get_usage(true)+$estimate>$bytes) return null;
        $image=match($media->mime){'image/jpeg'=>@imagecreatefromjpeg($source),'image/png'=>@imagecreatefrompng($source),'image/webp'=>@imagecreatefromwebp($source)};
        if (!$image) return null;
        $output=null;$tmp=null;
        try {
            $o=$info['orientation'];
            if (in_array($o,[2,5,7],true)) imageflip($image,IMG_FLIP_HORIZONTAL);
            if ($o===4) imageflip($image,IMG_FLIP_VERTICAL);
            $angle=match($o){3=>180,5,8=>90,6,7=>-90,default=>0};
            if ($angle) { $rotated=imagerotate($image,$angle,0);imagedestroy($image);$image=$rotated; }
            $scale=min(1,$maxW/imagesx($image),$maxH/imagesy($image));
            $w=max(1,(int)round(imagesx($image)*$scale));$h=max(1,(int)round(imagesy($image)*$scale));
            $cw=$profile==='icon'?256:$w;$ch=$profile==='icon'?256:$h;
            $output=imagecreatetruecolor($cw,$ch);imagealphablending($output,false);imagesavealpha($output,true);
            imagefill($output,0,0,imagecolorallocatealpha($output,0,0,0,127));
            imagecopyresampled($output,$image,(int)(($cw-$w)/2),(int)(($ch-$h)/2),0,0,$w,$h,imagesx($image),imagesy($image));
            if (!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir)) return null;
            $tmp=tempnam($dir,'build-');
            if (!$tmp || !imagewebp($output,$tmp,85) || !rename($tmp,$path)) return null;
            return $path;
        } finally { imagedestroy($image);if($output)imagedestroy($output);if($tmp&&is_file($tmp))unlink($tmp); }
    }
}
