<?php
namespace App\Tests\Cms;
use App\Cms\ImageProcessor;
use App\Entity\Media;
use PHPUnit\Framework\TestCase;
final class ImageProcessorTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root=sys_get_temp_dir().'/gmc-variants-'.bin2hex(random_bytes(6));mkdir($this->root.'/public',0775,true); }
    protected function tearDown(): void {
        $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as $f){if($f->isDir())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($this->root);
    }
    private function raster(int $w,int $h): Media {
        $im=imagecreatetruecolor($w,$h);imagealphablending($im,false);imagesavealpha($im,true);
        imagefill($im,0,0,imagecolorallocatealpha($im,0,0,0,127));
        imagefilledrectangle($im,(int)($w/4),0,(int)($w*3/4),$h-1,imagecolorallocatealpha($im,200,20,20,0));
        imagepng($im,$this->root.'/public/test.png');imagedestroy($im);
        $m=new Media();$m->path='test.png';$m->mime='image/png';return $m;
    }
    public function testLogoResizingTransparencyCacheAndOriginalPreservation(): void {
        $m=$this->raster(1200,600);$service=new ImageProcessor($this->root);$hash=hash_file('sha256',$this->root.'/public/test.png');
        $path=$service->variant($m,'logo');self::assertNotNull($path);self::assertSame([480,240],array_slice(getimagesize($path),0,2));
        $im=imagecreatefromwebp($path);self::assertSame(127,(imagecolorat($im,0,0)>>24)&127);imagedestroy($im);
        self::assertSame($hash,hash_file('sha256',$this->root.'/public/test.png'));self::assertSame($path,$service->variant($m,'logo'));
        self::assertNull($service->variant($m,'../../bad'));self::assertNull($service->warning($m,'logo'));
    }
    public function testSmallImageIsNotEnlargedAndIconIsPadded(): void {
        $m=$this->raster(40,20);$service=new ImageProcessor($this->root);
        self::assertSame([40,20],array_slice(getimagesize($service->variant($m,'slide')),0,2));
        $icon=$service->variant($m,'icon');self::assertSame([256,256],array_slice(getimagesize($icon),0,2));
        $im=imagecreatefromwebp($icon);self::assertSame(127,(imagecolorat($im,0,0)>>24)&127);self::assertSame(0,(imagecolorat($im,128,128)>>24)&127);imagedestroy($im);
        self::assertStringContainsString('résolution faible',$service->warning($m,'slide'));
    }
    public function testVectorAndOutsidePathsAreNotProcessed(): void {
        $m=new Media();$m->path='../outside.png';$m->mime='image/png';$service=new ImageProcessor($this->root);
        self::assertNull($service->path($m));self::assertNull($service->variant($m,'logo'));
        $m->mime='image/svg+xml';self::assertFalse($service->supported($m));self::assertNull($service->warning($m,'logo'));
    }
}
