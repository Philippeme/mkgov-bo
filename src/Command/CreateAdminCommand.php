<?php

namespace App\Command;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create admin user with correct password hash',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // Check if admin user already exists by username OR email
            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'admin']);
            $existingUserByEmail = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@mkgov.cm']);
            
            if ($existingUser || $existingUserByEmail) {
                $user = $existingUser ?: $existingUserByEmail;
                $io->warning('Admin user already exists. Updating...');
                
                // Update all fields
                $user->setUsername('admin');
                $user->setEmail('admin@mkgov.cm');
                $user->setFirstName('System');
                $user->setLastName('Administrator');
                $user->setIsActive(true);
                $user->setIsVerified(true);
            } else {
                $io->info('Creating new admin user...');
                $user = new User();
                $user->setUsername('admin');
                $user->setEmail('admin@mkgov.cm');
                $user->setFirstName('System');
                $user->setLastName('Administrator');
                $user->setIsActive(true);
                $user->setIsVerified(true);
                $user->setDisplayOrder(1);
                $this->entityManager->persist($user);
            }

            // Hash the password correctly
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'admin123');
            $user->setPassword($hashedPassword);

            // Find or create SUPER_ADMIN role
            $superAdminRole = $this->entityManager->getRepository(Role::class)->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
            
            if (!$superAdminRole) {
                $io->info('Creating ROLE_SUPER_ADMIN...');
                $superAdminRole = new Role();
                $superAdminRole->setName('ROLE_SUPER_ADMIN');
                $superAdminRole->setDisplayName('Super Administrator');
                $superAdminRole->setDescription('Full system access and administration rights');
                $superAdminRole->setIsSystem(true);
                $superAdminRole->setIsActive(true);
                $superAdminRole->setDisplayOrder(1);
                $this->entityManager->persist($superAdminRole);
            }

            // Assign role to user
            $user->addRole($superAdminRole);

            if (!$existingUser) {
                $this->entityManager->persist($user);
            }

            $this->entityManager->flush();

            $io->success('Admin user created/updated successfully!');
            $io->info('Username: admin');
            $io->info('Password: admin123');
            $io->info('You can now login to the admin panel.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error creating admin user: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}