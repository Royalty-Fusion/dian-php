<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Bridge\Symfony\Command;

use RoyaltyFusion\DianPhp\Tools\Doctor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'dian:doctor', description: 'Diagnostica entorno, certificado y conexión con la DIAN antes de habilitar o desplegar.')]
final class DianDoctorCommand extends Command
{
    public function __construct(
        private readonly string $certPath,
        private readonly string $certPassword,
        private readonly string $environment,
        private readonly string $softwareId = '',
        private readonly string $providerNit = '',
        private readonly string $nit = '',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('nit', null, InputOption::VALUE_REQUIRED, 'NIT del facturador (sin DV).')
            ->addOption('software-id', null, InputOption::VALUE_REQUIRED, 'SoftwareID asignado por la DIAN.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $nit    = (string) ($input->getOption('nit') ?: $this->nit) ?: null;
        $soft   = (string) ($input->getOption('software-id') ?: $this->softwareId) ?: null;
        $doctor = new Doctor();

        $io->title('Diagnóstico DIAN (' . $this->environment . ')');
        $report = $doctor->run($this->certPath, $this->certPassword, $this->environment, $nit, $soft, $this->providerNit ?: $nit);
        $output->writeln($report->toText());

        foreach ($doctor->getRanges() as $range) {
            $io->definitionList(
                ['Resolución'    => $range['ResolutionNumber'] ?? '—'],
                ['Prefijo'       => $range['Prefix'] ?? '—'],
                ['Rango'         => ($range['FromNumber'] ?? '?') . ' - ' . ($range['ToNumber'] ?? '?')],
                ['Vigencia'      => ($range['ValidDateFrom'] ?? '?') . ' a ' . ($range['ValidDateTo'] ?? '?')],
                ['Clave técnica' => $range['TechnicalKey'] ?? '—'],
            );
        }

        return $report->isReady() ? Command::SUCCESS : Command::FAILURE;
    }
}
