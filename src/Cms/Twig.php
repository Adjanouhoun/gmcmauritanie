<?php
namespace App\Cms;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
final class Twig extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private Content $cms) {}
    public function getGlobals(): array { return ['cms'=>$this->cms]; }
}
