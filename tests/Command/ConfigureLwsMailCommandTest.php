<?php
namespace App\Tests\Command;
use App\Command\ConfigureLwsMailCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Dotenv\Dotenv;
final class ConfigureLwsMailCommandTest extends TestCase
{
    public function testConfigurationPreservesOtherVariablesAndHidesPassword(): void
    {
        $dir=sys_get_temp_dir().'/gmc-mail-'.bin2hex(random_bytes(6));mkdir($dir,0700);
        try {
            file_put_contents($dir.'/.env.local',"APP_SECRET=preserved\nMAILER_DSN=null://null\nMAILER_TO=old@example.org\n");
            $tester=new CommandTester(new ConfigureLwsMailCommand($dir));
            $password='Secret:@/$# &"';
            $tester->setInputs(['mail28.lwspanel.com','sender@example.org','inbox@example.org',$password]);
            self::assertSame(0,$tester->execute([],['interactive'=>true]));
            $raw=file_get_contents($dir.'/.env.local');$values=(new Dotenv())->parse($raw);
            self::assertSame('preserved',$values['APP_SECRET']);
            self::assertSame('smtps://sender%40example.org:'.rawurlencode($password).'@mail28.lwspanel.com:465',$values['MAILER_DSN']);
            self::assertSame('sender@example.org',$values['MAILER_FROM']);self::assertSame('inbox@example.org',$values['MAILER_TO']);
            self::assertSame(1,substr_count($raw,'MAILER_DSN='));self::assertSame(0600,fileperms($dir.'/.env.local')&0777);
            self::assertStringNotContainsString($password,$tester->getDisplay());self::assertStringNotContainsString(rawurlencode($password),$tester->getDisplay());
        } finally { if (is_file($dir.'/.env.local')) unlink($dir.'/.env.local');rmdir($dir); }
    }
    public function testNonInteractiveRunDoesNotWrite(): void
    {
        $tester=new CommandTester(new ConfigureLwsMailCommand('/not-used'));
        self::assertSame(1,$tester->execute([],['interactive'=>false]));
    }
}
