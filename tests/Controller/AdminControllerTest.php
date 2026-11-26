<?php
namespace App\Tests\Controller;
use App\Entity\{AdminUser,CmsEntry,Media,Homepage,ServiceOffering,Training};
use App\Cms\Html;
use App\Command\ImportCmsCommand;
use Doctrine\ORM\{EntityManagerInterface,Tools\SchemaTool};
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\UploadedFile;
final class AdminControllerTest extends WebTestCase
{
    protected static function getKernelClass(): string {return \App\Kernel::class;}
    private string $database;
    private $client;
    private AdminUser $user;
    private array $uploads=[];
    private array $previousEnv=[];
    protected function setUp(): void
    {
        $this->previousEnv=[$_ENV['DATABASE_URL']??null,$_SERVER['DATABASE_URL']??null];
        $this->database=tempnam(sys_get_temp_dir(),'gmc-cms-test-');$_ENV['DATABASE_URL']=$_SERVER['DATABASE_URL']='sqlite:///'.$this->database;
        $this->client=self::createClient();$em=self::getContainer()->get(EntityManagerInterface::class);$em->getConfiguration()->setUseSchemaEditorApi(true);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $this->user=new AdminUser('admin@example.org');$this->user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($this->user,'Test-password-only-123'));$em->persist($this->user);$em->persist(new Homepage());
        foreach(json_decode(file_get_contents(__DIR__.'/../Fixtures/services.json'),true) as $row){$item=new ServiceOffering($row['slug']);foreach(['title','summary','tagline','listTitle','description'] as $key)$item->$key=$row[$key];$em->persist($item);}
        foreach(json_decode(file_get_contents(__DIR__.'/../Fixtures/trainings.json'),true) as $row){$item=new Training($row['slug'],$row['featured']);foreach(['title','shortTitle','description','objectives','audience','duration','note','summary','homeDuration'] as $key)$item->$key=$row[$key];$em->persist($item);}
        $em->flush();(new CommandTester(self::getContainer()->get(ImportCmsCommand::class)))->execute([]);
    }
    protected function tearDown(): void
    {
        foreach($this->uploads as $path)if(is_file($path))unlink($path);
        parent::tearDown();if(isset($this->database)&&is_file($this->database))unlink($this->database);
        foreach(['_ENV','_SERVER'] as $i=>$global){if($this->previousEnv[$i]===null)unset($GLOBALS[$global]['DATABASE_URL']);else $GLOBALS[$global]['DATABASE_URL']=$this->previousEnv[$i];}
    }
    private function entry(string $type,string $slug='main'): CmsEntry {return self::getContainer()->get(EntityManagerInterface::class)->getRepository(CmsEntry::class)->findOneBy(['type'=>$type,'slug'=>$slug]);}
    private function edit(CmsEntry $entry): string {return '/admin/contenus/'.$entry->type.'/'.$entry->getId().'/modifier';}
    private function login(): void {$this->client->loginUser($this->user);}
    public function testCustomPagePublicationAndMenuDestinations(): void
    {
        $this->login();$em=self::getContainer()->get(EntityManagerInterface::class);$before=$em->getRepository(CmsEntry::class)->count(['type'=>'menu']);
        $url='/admin/contenus/custom_page/nouveau';$crawler=$this->client->request('GET',$url);
        $data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['title']='Notre qualité';$data['status']='draft';$data['data']['body']='<p>Notre engagement qualité.</p><script>alert(1)</script>';
        $this->client->request('POST',$url,$data);self::assertResponseRedirects();$entry=$this->entry('custom_page','notre-qualite');$edit=$this->edit($entry);$id=$entry->getId();
        self::assertSame($before,self::getContainer()->get(EntityManagerInterface::class)->getRepository(CmsEntry::class)->count(['type'=>'menu']));
        $this->client->request('GET','/pages/notre-qualite');self::assertResponseStatusCodeSame(404);
        $this->client->request('GET','/admin/apercu/'.$id);self::assertResponseIsSuccessful();self::assertSelectorTextContains('h1','Notre qualité');self::assertSelectorNotExists('.cms-rich script');
        $crawler=$this->client->request('GET',$edit);$this->client->submit($crawler->selectButton('Enregistrer')->form(['status'=>'published']));self::assertResponseRedirects($edit);
        $this->client->request('GET','/pages/notre-qualite');self::assertResponseIsSuccessful();self::assertSelectorTextContains('.cms-rich','Notre engagement qualité.');
        $url='/admin/contenus/menu/nouveau';$crawler=$this->client->request('GET',$url);$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['title']='Qualité';$data['status']='published';$data['data']['link']='/pages/notre-qualite';unset($data['data']['header']);$data['data']['footer']='1';
        $this->client->request('POST',$url,$data);self::assertResponseRedirects();$menuEdit=$this->edit($this->entry('menu','qualite'));
        $this->client->request('GET','/');self::assertSelectorNotExists('#gmc-navigation a[href="/pages/notre-qualite"]');self::assertSelectorExists('.gmc-footer a[href="/pages/notre-qualite"]');
        $crawler=$this->client->request('GET',$edit);$this->client->submit($crawler->selectButton('Enregistrer')->form(['status'=>'archived']));self::assertResponseRedirects($edit);
        $this->client->request('GET','/');self::assertSelectorNotExists('.gmc-footer a[href="/pages/notre-qualite"]');$this->client->request('GET','/pages/notre-qualite');self::assertResponseStatusCodeSame(404);
        $crawler=$this->client->request('GET',$menuEdit);$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['data']['link']='/page-inexistante';$this->client->request('POST',$menuEdit,$data);self::assertResponseStatusCodeSame(422);
        $data['data']['link']='external';$data['data']['externalUrl']='javascript:alert(1)';$this->client->request('POST',$menuEdit,$data);self::assertResponseStatusCodeSame(422);
        $data['data']['externalUrl']='https://example.org/';$this->client->request('POST',$menuEdit,$data);self::assertResponseRedirects($menuEdit);
        $this->client->request('GET','/');self::assertSelectorExists('.gmc-footer a[href="https://example.org/"]');
    }
    public function testAnonymousVisitorsCannotAccessAdminOrPreview(): void
    {
        foreach(['/admin','/admin/contenus/service','/admin/medias','/admin/apercu/1'] as $path){$this->client->request('GET',$path);self::assertResponseRedirects('/admin/connexion');}
        $this->client->request('POST','/admin/contenus/service/nouveau');self::assertResponseRedirects('/admin/connexion');
    }
    public function testLoginAndLogout(): void
    {
        $crawler=$this->client->request('GET','/admin/connexion');$this->client->submit($crawler->selectButton('Se connecter')->form(['_username'=>'admin@example.org','_password'=>'Test-password-only-123']));self::assertResponseRedirects('http://localhost/admin');$crawler=$this->client->followRedirect();self::assertResponseIsSuccessful();$this->client->submit($crawler->selectButton('Se déconnecter')->form());self::assertResponseRedirects('/admin/connexion');$this->client->request('GET','/admin');self::assertResponseRedirects('/admin/connexion');
    }
    public function testImportIsIdempotentAndKeepsExistingContent(): void
    {
        $em=self::getContainer()->get(EntityManagerInterface::class);$count=$em->getRepository(CmsEntry::class)->count([]);$home=$this->entry('home');$home->title='Titre conservé';$em->flush();(new CommandTester(self::getContainer()->get(ImportCmsCommand::class)))->execute([]);self::assertSame($count,$em->getRepository(CmsEntry::class)->count([]));self::assertSame('Titre conservé',$this->entry('home')->title);self::assertCount(6,$em->getRepository(CmsEntry::class)->findBy(['type'=>'service']));self::assertCount(11,$em->getRepository(CmsEntry::class)->findBy(['type'=>'training']));
    }
    public function testAllPublicPagesAndLegacyLinksWork(): void
    {
        foreach(['/','/index.html','/services','/formation','/a-propos','/contact','/realisations','/services/construction-metallique','/formations/soudure'] as $path){$this->client->request('GET',$path);self::assertResponseIsSuccessful();}
        $this->client->request('GET','/service-construction-metallique.html');self::assertResponseRedirects('/services/construction-metallique',301);
    }
    public function testCreatePublishArchiveAndRestoreService(): void
    {
        $this->login();$media=self::getContainer()->get(EntityManagerInterface::class)->getRepository(Media::class)->findOneBy(['mime'=>'image/svg+xml']);
        $crawler=$this->client->request('GET','/admin/contenus/service/nouveau');$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['title']='Nouveau service';$data['slug']='nouveau-service';$data['data']['icon']=(string)$media->getId();$data['data']['summary']='Présentation du nouveau service';$data['data']['body']='<p>Un service <strong>sur mesure</strong><script>alert(1)</script></p>';$data['data']['featured']='1';
        $this->client->request('POST','/admin/contenus/service/nouveau',$data);self::assertResponseRedirects();$entry=$this->entry('service','nouveau-service');$url=$this->edit($entry);$id=$entry->getId();
        $this->client->request('GET','/services/nouveau-service');self::assertResponseStatusCodeSame(404);
        $this->client->request('GET','/admin/apercu/'.$id);self::assertResponseIsSuccessful();self::assertSelectorTextContains('body','sur mesure');self::assertStringNotContainsString('<script>alert(1)</script>',$this->client->getResponse()->getContent());
        foreach([['published',200],['archived',404],['published',200]] as [$state,$code]){
            $crawler=$this->client->request('GET',$url);$this->client->submit($crawler->selectButton('Enregistrer')->form(['status'=>$state]));self::assertResponseRedirects();$this->client->request('GET','/services/nouveau-service');self::assertResponseStatusCodeSame($code);
        }
        $this->client->request('GET','/');self::assertSelectorTextContains('body','Nouveau service');
    }
    public function testCanCreateTrainingAndItsPublicPage(): void
    {
        $this->login();$media=self::getContainer()->get(EntityManagerInterface::class)->getRepository(Media::class)->findOneBy(['mime'=>'image/svg+xml']);$crawler=$this->client->request('GET','/admin/contenus/training/nouveau');$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['title']='Nouvelle formation';$data['status']='published';$data['data']=array_replace($data['data'],['shortTitle'=>'Nouveau programme','icon'=>(string)$media->getId(),'body'=>'<p>Programme de test</p>','objectives'=>"Premier objectif\nDeuxième objectif",'audience'=>'Techniciens','duration'=>'3 jours','featured'=>'1','summary'=>'Aperçu de test']);$this->client->request('POST','/admin/contenus/training/nouveau',$data);self::assertResponseRedirects();$this->client->request('GET','/formations/nouvelle-formation');self::assertResponseIsSuccessful();self::assertSelectorTextContains('body','Deuxième objectif');$this->client->request('GET','/formation');self::assertSelectorExists('#formation-nouvelle-formation');$this->client->request('GET','/');self::assertSelectorTextContains('body','Nouveau programme');
    }
    public function testCsrfValidationAndConcurrentEdits(): void
    {
        $this->login();$url=$this->edit($this->entry('home'));$this->client->request('POST',$url,['_token'=>'bad']);self::assertResponseStatusCodeSame(403);$crawler=$this->client->request('GET',$url);$invalid=$crawler->selectButton('Enregistrer')->form(['title'=>'']);$this->client->submit($invalid);self::assertResponseStatusCodeSame(422);$crawler=$this->client->request('GET',$url);$stale=$crawler->selectButton('Enregistrer')->form(['title'=>'Ancien']);$fresh=$crawler->selectButton('Enregistrer')->form(['title'=>'Titre actuel']);$this->client->submit($fresh);self::assertResponseRedirects();$this->client->submit($stale);self::assertResponseStatusCodeSame(409);$this->client->request('GET','/');self::assertSelectorTextContains('h1','Titre actuel');
    }
    public function testUnsafeUrlsAndInvalidMediaAreRejected(): void
    {
        $this->login();$url=$this->edit($this->entry('slide','slide-1'));$crawler=$this->client->request('GET',$url);$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['data']['link']='javascript:alert(1)';$data['data']['image']='999999';$this->client->request('POST',$url,$data);self::assertResponseStatusCodeSame(422);
        self::assertFalse(Html::safeUrl('//evil.example'));self::assertFalse(Html::safeUrl('/\\evil.example'));self::assertTrue(Html::safeUrl('/services'));
        $safe=Html::sanitize('<p onclick="bad()">Texte <strong>utile</strong><img src=x onerror=bad()><a href="javascript:bad()">lien</a><script>bad()</script></p>');self::assertStringContainsString('<strong>utile</strong>',$safe);self::assertStringNotContainsString('javascript:',$safe);self::assertStringNotContainsString('onerror',$safe);self::assertStringNotContainsString('<script',$safe);
    }
    public function testContactAndSliderChangesArePublic(): void
    {
        $this->login();$url=$this->edit($this->entry('contact'));$crawler=$this->client->request('GET',$url);$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['data']['phone']='+222 12 34 56 78';$data['data']['email']='info@example.org';$data['data']['address']='Nouvelle adresse';$this->client->request('POST',$url,$data);self::assertResponseRedirects();$this->client->request('GET','/contact');self::assertSelectorExists('a[href="mailto:info@example.org"]');self::assertSelectorTextContains('address','Nouvelle adresse');$this->client->request('GET','/');self::assertSelectorExists('a[href="tel:+22212345678"]');
        $url=$this->edit($this->entry('slide','slide-1'));$crawler=$this->client->request('GET',$url);$this->client->submit($crawler->selectButton('Enregistrer')->form(['title'=>'Nouveau slider','position'=>'99']));self::assertResponseRedirects();$crawler=$this->client->request('GET','/');self::assertSelectorTextContains('.cms-slide:last-child','Nouveau slider');
    }
    public function testMediaRejectsExecutableAndAcceptsImage(): void
    {
        $this->login();$crawler=$this->client->request('GET','/admin/medias');$token=$crawler->filter('input[name="_token"]')->first()->attr('value');$tmp=tempnam(sys_get_temp_dir(),'gmc-file-');file_put_contents($tmp,'<?php echo "unsafe";');$this->uploads[]=$tmp;$this->client->request('POST','/admin/medias',['_token'=>$token],['file'=>new UploadedFile($tmp,'image.php','image/jpeg',null,true)]);self::assertResponseStatusCodeSame(422);
        $crawler=$this->client->request('GET','/admin/medias');$token=$crawler->filter('input[name="_token"]')->first()->attr('value');$tmp=tempnam(sys_get_temp_dir(),'gmc-image-');file_put_contents($tmp,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jGZkAAAAASUVORK5CYII='));$this->uploads[]=$tmp;$this->client->request('POST','/admin/medias',['_token'=>$token,'alt'=>'Image de test'],['file'=>new UploadedFile($tmp,'test.png','image/png',null,true)]);self::assertResponseRedirects('/admin/medias');$media=self::getContainer()->get(EntityManagerInterface::class)->getRepository(Media::class)->findOneBy(['uploaded'=>true]);self::assertNotNull($media);$this->uploads[]=dirname(__DIR__,2).'/var/media/'.$media->path;$this->client->request('GET','/media/'.$media->getId());self::assertResponseIsSuccessful();self::assertResponseHeaderSame('X-Content-Type-Options','nosniff');
    }

    public function testInlineMediaUploadAndFreshCatalogue(): void
    {
        $this->login();$url=$this->edit($this->entry('service','construction-metallique'));
        $crawler=$this->client->request('GET',$url);
        self::assertSelectorExists('form[data-inline-upload]');
        $token=$crawler->filter('form[data-inline-upload] input[name="_token"]')->attr('value');
        $tmp=tempnam(sys_get_temp_dir(),'gmc-inline-');
        file_put_contents($tmp,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jGZkAAAAASUVORK5CYII='));
        $this->uploads[]=$tmp;
        $this->client->request('POST','/admin/medias',['_token'=>$token,'alt'=>'Nouvelle icône'],['file'=>new UploadedFile($tmp,'icone-test.png','image/png',null,true)],['HTTP_ACCEPT'=>'application/json']);
        self::assertResponseStatusCodeSame(201);
        $item=json_decode($this->client->getResponse()->getContent(),true,flags:JSON_THROW_ON_ERROR)['item'];
        self::assertSame('image',$item['kind']);self::assertSame('Nouvelle icône',$item['alt']);
        $media=self::getContainer()->get(EntityManagerInterface::class)->find(Media::class,$item['id']);
        $this->uploads[]=dirname(__DIR__,2).'/var/media/'.$media->path;
        $this->client->request('GET','/admin/medias/catalogue');self::assertResponseIsSuccessful();
        $catalogue=json_decode($this->client->getResponse()->getContent(),true,flags:JSON_THROW_ON_ERROR);
        self::assertGreaterThan(0,$catalogue['maxBytes']);self::assertSame($item['id'],$catalogue['items'][0]['id']);
        // Newly imported media can be saved immediately into the existing form.
        $data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['data']['icon']=(string)$item['id'];
        $this->client->request('POST',$url,$data);self::assertResponseRedirects();
        $this->client->request('GET','/');self::assertSelectorExists('img[src="/media/'.$item['id'].'/variant/icon"]');
    }
    public function testInlineUploadErrorsAreActionable(): void
    {
        $this->login();
        $this->client->request('POST','/admin/medias',['_token'=>'invalid'],[],['HTTP_ACCEPT'=>'application/json']);
        self::assertResponseStatusCodeSame(403);self::assertStringContainsString('session',json_decode($this->client->getResponse()->getContent(),true)['error']);
        $this->client->request('POST','/admin/medias',[],[],['HTTP_ACCEPT'=>'application/json','CONTENT_LENGTH'=>'999999999999']);
        self::assertResponseStatusCodeSame(422);self::assertStringContainsString('limite',json_decode($this->client->getResponse()->getContent(),true)['error']);
    }
    public function testMalformedGalleryIsRejectedWithoutServerError(): void
    {
        $this->login();$url=$this->edit($this->entry('service','construction-metallique'));$crawler=$this->client->request('GET',$url);
        $data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['data']['gallery']=[['nested'=>'invalid'],'not-an-id'];
        $this->client->request('POST',$url,$data);self::assertResponseStatusCodeSame(422);self::assertSelectorTextContains('[role="alert"]','image invalide');
    }
    public function testPreviewOverrideIsClearedAfterRendering(): void
    {
        $this->login();$entry=$this->entry('service','construction-metallique');$id=$entry->getId();
        $this->client->disableReboot();$this->client->request('GET','/admin/apercu/'.$id);self::assertResponseIsSuccessful();
        self::assertNull(self::getContainer()->get(\App\Cms\Content::class)->preview);
        $this->client->request('GET','/');self::assertSelectorNotExists('.cms-preview-bar');
    }
    public function testSliderFocusValidationAndSmallImageWarning(): void
    {
        $this->login();$url=$this->edit($this->entry('slide','slide-1'));
        $em=self::getContainer()->get(EntityManagerInterface::class);
        $m=new Media();$m->path=str_repeat('a',40).'.png';$m->uploaded=true;$m->mime='image/png';$m->name='tiny.png';
        $path=dirname(__DIR__,2).'/var/media/'.$m->path;$this->uploads[]=$path;
        $im=imagecreatetruecolor(10,10);imagepng($im,$path);imagedestroy($im);$em->persist($m);$em->flush();$id=$m->getId();
        $crawler=$this->client->request('GET',$url);$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $data['data']['image']=(string)$id;$data['data']['focusX']='101';
        $this->client->request('POST',$url,$data);self::assertResponseStatusCodeSame(422);
        $data['data']['focusX']='0';$data['data']['focusY']='100';$data['data']['mobileFocusX']='25';
        $this->client->request('POST',$url,$data);self::assertResponseRedirects();$this->client->followRedirect();
        self::assertSelectorTextContains('[data-image-warning]','résolution faible');self::assertSelectorExists('[data-crop="mobile"]');
        $this->client->request('GET','/');self::assertSelectorExists('.cms-slide[style*="--focus-x:0%"]');
        $this->client->request('GET','/media/'.$id.'/variant/not-allowed');self::assertResponseStatusCodeSame(404);
        $this->client->request('GET','/admin/medias/catalogue');$items=json_decode($this->client->getResponse()->getContent(),true)['items'];
        self::assertSame(10,$items[0]['width']);self::assertSame(10,$items[0]['height']);
    }

    public function testProjectCategoriesAndPortfolioDetails(): void
    {
        $command=new CommandTester(self::getContainer()->get(\App\Command\OrganizeProjectsCommand::class));$command->execute([]);
        $em=self::getContainer()->get(EntityManagerInterface::class);$count=$em->getRepository(CmsEntry::class)->count(['type'=>'project_category']);
        self::assertGreaterThan(0,$count);$command->execute([]);self::assertSame($count,$em->getRepository(CmsEntry::class)->count(['type'=>'project_category']));
        $project=$em->getRepository(CmsEntry::class)->findOneBy(['type'=>'project']);$id=$project->getId();$url=$this->edit($project);$slug=$project->slug;
        $category=$em->find(CmsEntry::class,$project->data['categoryId']);$categoryId=$category->getId();$categoryUrl=$this->edit($category);$originalLocation=$project->data['location'];
        $this->login();$crawler=$this->client->request('GET',$url);$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $data['data']['year']='2026';$data['data']['summary']='Un projet de référence';$data['data']['work']='<p>Montage sur site</p>';$data['data']['results']='<p>Travaux réceptionnés</p>';$data['data']['client']='Client autorisé';$data['data']['featured']='1';$data['data']['focusX']='25';
        $this->client->request('POST',$url,$data);self::assertResponseRedirects();
        $this->client->request('GET','/realisations');self::assertSelectorTextContains('[data-project-feature]','Un projet de référence');self::assertSelectorExists('[data-project-filter="'.$categoryId.'"]');self::assertSelectorCount($count+1,'[data-project-filter]');
        $this->client->request('GET','/realisations/'.$slug);self::assertResponseIsSuccessful();self::assertSelectorTextContains('h1',$project->title);self::assertSelectorTextContains('.project-story','Montage sur site');self::assertSelectorTextContains('.project-facts',$originalLocation);self::assertSelectorTextContains('.project-facts','2026');self::assertSelectorExists('.project-detail-cover img[style*="25%"]');
        $crawler=$this->client->request('GET',$categoryUrl);$this->client->submit($crawler->selectButton('Enregistrer')->form(['title'=>'Activité renommée']));self::assertResponseRedirects();
        $this->client->request('GET','/realisations/'.$slug);self::assertSelectorTextContains('.project-facts','Activité renommée');
        $crawler=$this->client->request('GET',$categoryUrl);$this->client->submit($crawler->selectButton('Enregistrer')->form(['status'=>'archived']));self::assertResponseRedirects();
        $this->client->request('GET','/realisations');self::assertSelectorNotExists('[data-project-filter="'.$categoryId.'"]');
        $this->client->request('GET','/realisations/'.$slug);self::assertResponseIsSuccessful();self::assertSelectorTextNotContains('.project-facts','Activité renommée');
    }
    public function testProjectValidationFeaturedSelectionAndOrderedGallery(): void
    {
        $this->login();$em=self::getContainer()->get(EntityManagerInterface::class);$projects=$em->getRepository(CmsEntry::class)->findBy(['type'=>'project'],['id'=>'ASC']);
        $first=$projects[0];$first->data['featured']=true;$em->flush();$firstId=$first->getId();$second=$projects[1];$secondId=$second->getId();$slug=$second->slug;$url=$this->edit($second);
        $media=$em->getRepository(Media::class)->findBy(['mime'=>'image/jpeg'],[],2);$ids=array_map(fn($m)=>(string)$m->getId(),$media);
        $crawler=$this->client->request('GET',$url);$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['data']['categoryId']=(string)$firstId;$data['data']['year']='abc';
        $this->client->request('POST',$url,$data);self::assertResponseStatusCodeSame(422);
        $data['data']['categoryId']='';$data['data']['year']='2025';$data['data']['featured']='1';$data['data']['gallery']=array_reverse($ids);
        $this->client->request('POST',$url,$data);self::assertResponseRedirects();
        $em=self::getContainer()->get(EntityManagerInterface::class);self::assertFalse($em->find(CmsEntry::class,$firstId)->data['featured']);self::assertTrue($em->find(CmsEntry::class,$secondId)->data['featured']);
        $crawler=$this->client->request('GET','/realisations/'.$slug);self::assertResponseIsSuccessful();self::assertSame('/media/'.$ids[1].'/variant/photo',$crawler->filter('[data-project-photo]')->first()->attr('href'));self::assertSelectorExists('.project-lightbox');self::assertSelectorNotExists('.project-story h2:contains("Les résultats")');
        $crawler=$this->client->request('GET',$url);$this->client->submit($crawler->selectButton('Enregistrer')->form(['status'=>'draft']));self::assertResponseRedirects();
        $this->client->request('GET','/realisations/'.$slug);self::assertResponseStatusCodeSame(404);
    }

    public function testNavigationIncludesPublishedServicesAndActiveParent(): void
    {
        $crawler=$this->client->request('GET','/services/construction-metallique');
        self::assertResponseIsSuccessful();self::assertSelectorExists('.gmc-nav-link[href="/services"][aria-current="location"]');
        self::assertSelectorExists('.gmc-submenu a[href="/services/construction-metallique"][aria-current="page"]');
        self::assertSelectorExists('.gmc-contact-link[href="/contact"]');self::assertSelectorExists('a[href="#main-content"]');
        $em=self::getContainer()->get(EntityManagerInterface::class);$service=$this->entry('service','construction-metallique');$service->status='draft';$em->flush();
        $this->client->request('GET','/');self::assertSelectorNotExists('.gmc-submenu a[href="/services/construction-metallique"]');
        $this->client->request('GET','/formations/soudure');self::assertSelectorExists('.gmc-nav-link[href="/formation"][aria-current="location"]');
    }

    public function testFooterContentCanBeManagedAndOptionalLinksStayHidden(): void
    {
        $this->client->request('GET','/');self::assertSelectorExists('.gmc-footer-invite');self::assertSelectorNotExists('.gmc-footer a[href="/mentions-legales"]');
        $this->login();$url=$this->edit($this->entry('settings'));$crawler=$this->client->request('GET',$url);
        self::assertTrue($crawler->filter('input[name="data[footerCtaVisible]"]')->attr('checked')!==null);
        $data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['data']['footerDescription']='Notre présentation personnalisée';$data['data']['footerCtaTitle']='Construisons votre projet';$data['data']['footerCtaButton']='Discutons';$data['data']['footerCtaLink']='/contact';$data['data']['legalUrl']='/mentions-legales';$data['data']['footerCtaVisible']='1';
        $this->client->request('POST',$url,$data);self::assertResponseRedirects();$this->client->request('GET','/services');
        self::assertSelectorTextContains('.gmc-footer-brand','Notre présentation personnalisée');self::assertSelectorTextContains('.gmc-footer-invite h2','Construisons votre projet');self::assertSelectorExists('.gmc-footer-button[href="/contact"]');self::assertSelectorExists('.gmc-footer a[href="/mentions-legales"]');
        $crawler=$this->client->request('GET',$url);$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();unset($data['data']['footerCtaVisible']);$this->client->request('POST',$url,$data);self::assertResponseRedirects();$this->client->request('GET','/');self::assertSelectorNotExists('.gmc-footer-invite');
        $em=self::getContainer()->get(EntityManagerInterface::class);$service=$this->entry('service','construction-metallique');$service->status='draft';$em->flush();$this->client->request('GET','/');self::assertSelectorNotExists('.gmc-footer a[href="/services/construction-metallique"]');
    }

    public function testEveryAdminSectionAndExistingFormCanBeSavedAndPreviewed(): void
    {
        $this->login();(new CommandTester(self::getContainer()->get(\App\Command\OrganizeProjectsCommand::class)))->execute([]);
        $em=self::getContainer()->get(EntityManagerInterface::class);
        $rows=array_map(fn($e)=>[$e->getId(),$e->type,$e->slug],$em->getRepository(CmsEntry::class)->findAll());
        foreach(\App\Cms\Schema::TYPES as $type=>$label){
            $this->client->request('GET','/admin/contenus/'.$type);self::assertResponseIsSuccessful();
            $this->client->request('GET','/admin/contenus/'.$type.'/nouveau');
            self::assertResponseStatusCodeSame(in_array($type,\App\Cms\Schema::SINGLE)||$type==='page'?404:200);
        }
        foreach($rows as [$id,$type,$slug]){
            $url='/admin/contenus/'.$type.'/'.$id.'/modifier';$crawler=$this->client->request('GET',$url);self::assertResponseIsSuccessful();
            $this->client->submit($crawler->selectButton('Enregistrer')->form());$section=$type==='page'?array_search($slug,\App\Cms\Schema::PAGE_SECTIONS,true):false;self::assertResponseRedirects($section!==false?'/admin/contenus/'.$section.'/presentation':$url);
            $this->client->request('GET','/admin/apercu/'.$id);self::assertResponseIsSuccessful();self::assertResponseHeaderSame('X-Robots-Tag','noindex, nofollow');
        }
    }
    public function testWrongTypeAndSingletonArchiveAreRejected(): void
    {
        $this->login();$home=$this->entry('home');$this->client->request('GET','/admin/contenus/service/'.$home->getId().'/modifier');self::assertResponseStatusCodeSame(404);
        $url=$this->edit($home);$crawler=$this->client->request('GET',$url);$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['status']='archived';
        $this->client->request('POST',$url,$data);self::assertResponseStatusCodeSame(422);
        $this->client->request('GET','/admin/contenus/inconnu');self::assertResponseStatusCodeSame(404);
        $this->client->request('GET','/admin/contenus/service/999999/modifier');self::assertResponseStatusCodeSame(404);
    }
    public function testArchivedMediaIsRetainedButExcludedFromPicker(): void
    {
        $this->login();$media=self::getContainer()->get(EntityManagerInterface::class)->getRepository(Media::class)->findOneBy(['mime'=>'image/jpeg']);$id=$media->getId();
        $crawler=$this->client->request('GET','/admin/medias');$form=$crawler->filter('form[action="/admin/medias/'.$id.'"]')->form();$data=$form->getPhpValues();$data['archived']='1';
        $this->client->request('POST','/admin/medias/'.$id,$data);self::assertResponseRedirects();
        $this->client->request('GET','/admin/medias/catalogue');$items=json_decode($this->client->getResponse()->getContent(),true)['items'];self::assertNotContains($id,array_column($items,'id'));
        self::assertNotNull(self::getContainer()->get(EntityManagerInterface::class)->find(Media::class,$id));
        $this->client->request('POST','/admin/medias/'.$id,['_token'=>'invalid']);self::assertResponseStatusCodeSame(403);
    }

    public function testContactPhoneFormattingAndInvalidNumbers(): void
    {
        $this->login();$url=$this->edit($this->entry('contact'));$crawler=$this->client->request('GET',$url);$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();
        foreach(['(+222) 41 41 40 93','+222 41.41.40.93','41414093'] as $phone){
            $crawler=$this->client->request('GET',$url);$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['data']['phone']=$phone;$this->client->request('POST',$url,$data);self::assertResponseRedirects();
        }
        foreach(['++++++','(     )','123+456','abc123456'] as $phone){
            $crawler=$this->client->request('GET',$url);$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['data']['phone']=$phone;$this->client->request('POST',$url,$data);self::assertResponseStatusCodeSame(422);
        }
    }
    public function testSliderWarningFlashIsVisibleAfterSaving(): void
    {
        $this->login();$url=$this->edit($this->entry('slide','slide-1'));$crawler=$this->client->request('GET',$url);$this->client->submit($crawler->selectButton('Enregistrer')->form());self::assertResponseRedirects();$this->client->followRedirect();self::assertSelectorTextContains('.admin-main > .alert-warning','résolution faible');
    }

    public function testUserGuideIsProtectedAndContainsWorkingSectionLinks(): void
    {
        $this->client->request('GET','/admin/guide');self::assertResponseRedirects('/admin/connexion');
        $this->login();$crawler=$this->client->request('GET','/admin/guide');self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1','Guide d’utilisation');self::assertSelectorExists('[data-print-guide]');self::assertSelectorCount(11,'.guide-section');
        foreach($crawler->filter('.guide-toc a') as $link){$id=substr($link->getAttribute('href'),1);self::assertSelectorExists('#'.$id);}
        foreach($crawler->filter('.admin-guide a[href^="/admin/"]')->extract(['href']) as $url){$this->client->request('GET',$url);self::assertResponseIsSuccessful();}
        $this->client->request('GET','/admin');self::assertSelectorExists('.admin-sidebar a[href="/admin/guide"]');self::assertSelectorExists('.admin-main a[href="/admin/guide"]');
    }

    public function testPagePresentationIsGroupedAndSearchFieldsAreOptional(): void
    {
        $this->client->request('GET','/admin/contenus/service/presentation');self::assertResponseRedirects('/admin/connexion');
        $this->login();$this->client->request('GET','/admin');self::assertSelectorNotExists('.admin-sidebar a[href="/admin/contenus/page"]');self::assertSelectorNotExists('.admin-main a[href="/admin/contenus/page"]');self::assertSelectorTextContains('.admin-sidebar','Menu du site');
        foreach(\App\Cms\Schema::PAGE_SECTIONS as $type=>$slug){
            $this->client->request('GET','/admin/contenus/'.$type);self::assertSelectorExists('a[href="/admin/contenus/'.$type.'/presentation"]');
            $this->client->request('GET','/admin/contenus/'.$type.'/presentation');self::assertResponseIsSuccessful();self::assertSelectorExists('.admin-sidebar a[href="/admin/contenus/'.$type.'"][aria-current="page"]');self::assertSelectorExists('.admin-advanced:not([open]) input[name="data[seoTitle]"]');
        }
        $url='/admin/contenus/service/presentation';$crawler=$this->client->request('GET',$url);$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['title']='Nos expertises GMC';$data['data']['intro']='Une introduction personnalisée';$data['data']['seoTitle']='';$data['data']['seoDescription']='';
        $this->client->request('POST',$url,$data);self::assertResponseRedirects($url);$this->client->request('GET','/services');self::assertSelectorTextContains('h1','Nos expertises GMC');self::assertSelectorTextContains('title','Nos expertises GMC');self::assertSelectorExists('meta[name="description"][content="Une introduction personnalisée"]');self::assertSelectorTextContains('.gmc-nav-link[href="/services"]','Services');
        $crawler=$this->client->request('GET',$url);$data=$crawler->selectButton('Enregistrer')->form()->getPhpValues();$data['data']['seoTitle']=str_repeat('a',501);$this->client->request('POST',$url,$data);self::assertResponseStatusCodeSame(422);self::assertSelectorExists('.admin-advanced[open]');
        $this->client->request('GET','/admin/contenus/menu/presentation');self::assertResponseStatusCodeSame(404);
    }

}
