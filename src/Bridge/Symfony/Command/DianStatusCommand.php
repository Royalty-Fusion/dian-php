<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Bridge\Symfony\Command;

use RoyaltyFusion\DianPhp\Ws\SoapClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'dian:status', description: 'Consultar estado de un documento por su CUFE/CUDE.')]
final class DianStatusCommand extends Command
{
    public function __construct(private readonly SoapClient $soapClient)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('trackId', InputArgument::REQUIRED, 'CUFE / CUDE del documento a consultar.')
            ->addOption('zip', null, InputOption::VALUE_NONE, 'Descargar también el ApplicationResponse zipeado.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $trackId = (string) $input->getArgument('trackId');

        $io->title("Consulta DIAN para trackId={$trackId}");

        $status = $input->getOption('zip')
            ? $this->soapClient->getStatusZip($trackId)
            : $this->soapClient->getStatus($trackId);

        $io->definitionList(
            ['Válido'     => $status->isValid() ? 'Sí' : 'No'],
            ['Código'     => $status->getStatusCode() ?: '—'],
            ['Descripción' => $status->getStatusDescription() ?: '—'],
            ['Mensaje'    => $status->getStatusMessage() ?: '—'],
        );

        if ($status->getErrorMessages()) {
            $io->section('Errores reportados');
            $io->listing($status->getErrorMessages());
        }

        if ($status->getApplicationResponseXml() !== '') {
            $io->section('ApplicationResponse XML');
            $output->writeln($status->getApplicationResponseXml());
        }

        return $status->isValid() ? Command::SUCCESS : Command::FAILURE;
    }
}
