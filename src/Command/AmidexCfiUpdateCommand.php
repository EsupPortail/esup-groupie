<?php

namespace App\Command;

use App\Entity\CentreFinancier;
use App\Entity\Roles;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AmidexCfiUpdateCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'app:amidex-cfi-update';

    protected function configure()
    {
        $this
            ->setDescription('Met à jour les Ordonnateurs et Gestionnaires Budgétaire en leur ajoutant tous les CFI d\'AMIDEX')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $em = $this->getContainer()->get('doctrine')->getManager();

        $roles = $em->getRepository(Roles::class)->findByStructFinanciere(true);
        $CFIs = $em->getRepository(CentreFinancier::class)->findByAmidex(new \DateTime("now"));
        foreach ($roles as $role)
        {
            foreach ($CFIs as $CFI)
            {
                $role->addCentreFinancier($CFI);
            }
        }

        $em->persist($role);
        $em->flush();
        $io->success('Centres financiers des rôles mis à jour');

        return 0;
    }
}
