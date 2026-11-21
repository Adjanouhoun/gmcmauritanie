<?php
namespace App\Command;

use App\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Créer un administrateur avec un mot de passe saisi de façon masquée.')]
final class CreateAdminCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->isInteractive()) { $io->error('Cette commande nécessite une saisie interactive.'); return Command::FAILURE; }
        $email = $io->ask('Adresse e-mail', null, function ($value): string {
            if (!is_string($value) || !filter_var(trim($value), FILTER_VALIDATE_EMAIL) || mb_strlen($value)>180) throw new \RuntimeException('Saisissez une adresse e-mail valide.');
            return mb_strtolower(trim($value));
        });
        if ($this->em->getRepository(AdminUser::class)->findOneBy(['email'=>$email])) { $io->error('Ce compte existe déjà.'); return Command::FAILURE; }
        $password = $io->askHidden('Mot de passe (12 caractères minimum)', function ($value): string {
            if (!is_string($value) || mb_strlen($value)<12 || strlen($value)>72) throw new \RuntimeException('Utilisez au moins 12 caractères et au plus 72 octets.');
            return $value;
        });
        if ($io->askHidden('Confirmer le mot de passe') !== $password) { $io->error('Les mots de passe diffèrent.'); return Command::FAILURE; }
        $user = new AdminUser($email);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();
        $io->success('Administrateur créé. Connectez-vous sur /admin/connexion.');
        return Command::SUCCESS;
    }
}
