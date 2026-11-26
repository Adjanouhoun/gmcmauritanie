<?php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Dotenv\Dotenv;

#[AsCommand(name:'app:configure-lws-mail',description:'Configurer SMTP LWS localement avec un mot de passe masqué, sans envoyer de message.')]
final class ConfigureLwsMailCommand extends Command
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir) { parent::__construct(); }
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        $io=new SymfonyStyle($input,$output);
        if (!$input->isInteractive()) { $io->error('Utilisez un terminal interactif pour saisir le mot de passe de façon masquée.');return Command::FAILURE; }
        $path=$this->projectDir.'/.env.local';
        if (is_link($path)) { $io->error('Le fichier .env.local ne doit pas être un lien symbolique.');return Command::FAILURE; }
        $content=is_file($path)?file_get_contents($path):'';
        if ($content===false) { $io->error('Impossible de lire .env.local.');return Command::FAILURE; }
        try { $existing=(new Dotenv())->parse($content); } catch (\Throwable) { $io->error('Le fichier .env.local contient une erreur de syntaxe. Corrigez-le avant de continuer.');return Command::FAILURE; }
        foreach (['MAILER_DSN','MAILER_FROM','MAILER_TO'] as $key) {
            if (isset($existing[$key]) && preg_match('/[\r\n]/',$existing[$key])) { $io->error('Une variable de messagerie est multiligne. Corrigez-la avant de continuer.');return Command::FAILURE; }
        }
        $host=$io->ask('Serveur SMTP LWS','mail28.lwspanel.com',static function($value): string {
            if (!is_string($value) || !preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.lwspanel\.com$/i',$value)) throw new \RuntimeException('Saisissez le serveur mail indiqué dans LWS Panel, par exemple mail28.lwspanel.com.');
            return strtolower($value);
        });
        $validateEmail=static function($value): string { if (!is_string($value) || !filter_var($value,FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Adresse e-mail invalide.');return $value; };
        $sender=$io->ask('Adresse de la boîte LWS (identifiant et expéditeur)','contact@gmcmauritanie.com',$validateEmail);
        $recipient=$io->ask('Adresse de réception des demandes',$sender,$validateEmail);
        $question=new Question('Mot de passe de la boîte LWS');$question->setHidden(true)->setHiddenFallback(false);
        $question->setValidator(static function($value): string { if (!is_string($value) || $value==='') throw new \RuntimeException('Le mot de passe est obligatoire.');return $value; });
        $password=$io->askQuestion($question);
        $dsn='smtps://'.rawurlencode($sender).':'.rawurlencode($password).'@'.$host.':465';
        $content=preg_replace('/^[ \t]*(?:export[ \t]+)?MAILER_(?:DSN|FROM|TO)[ \t]*=.*(?:\R|$)/m','',$content);
        $sender=addcslashes($sender,'\\"$');$recipient=addcslashes($recipient,'\\"$');
        $content=rtrim($content)."\n\nMAILER_DSN=\"$dsn\"\nMAILER_FROM=\"$sender\"\nMAILER_TO=\"$recipient\"\n";
        $temp=tempnam($this->projectDir,'.smtp-');
        if ($temp===false) { $io->error('Impossible de préparer la configuration.');return Command::FAILURE; }
        try {
            if (!chmod($temp,0600) || file_put_contents($temp,$content,LOCK_EX)===false || !rename($temp,$path)) { $io->error('Impossible d’enregistrer la configuration.');return Command::FAILURE; }
        } finally { if (is_file($temp)) unlink($temp); }
        $io->success('Configuration enregistrée dans .env.local (permissions 600). Aucun message envoyé.');
        $io->note('Redémarrez le serveur PHP si nécessaire, puis testez le formulaire et vérifiez la réception dans le webmail LWS.');
        return Command::SUCCESS;
    }
}
