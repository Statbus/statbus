<?php

namespace App\Controller;

use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\ServerInformationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class TicketController extends AbstractController
{

    public function __construct(
        private TicketRepository $ticketRepository,
        private ServerInformationService $serverInformationService,
        private UserRepository $userRepository
    ) {}

    #[Route("/tickets/{page}", name: 'tickets')]
    public function index(int $page = 1): Response
    {
        if ($this->isGranted('ROLE_BAN')) {
            $tgdb = true;
            $tickets = $this->ticketRepository->getTickets($page);
        } else {
            $tgdb = false;
            $tickets = $this->ticketRepository->getTicketsByCkey(
                $this->getUser()->getCkey(),
                $page
            );
        }
        return $this->render('ticket/index.html.twig', [
            'pagination' => $tickets,
            'tgdb' => $tgdb,
        ]);
    }

    #[Route("/tickets/server/{server}/{page}", name: 'server.tickets')]
    public function getTicketsForServer(string $server, int $page = 1): Response
    {
        $this->denyAccessUnlessGranted('ROLE_BAN');
        $server = $this->serverInformationService->getServerByIdentifier($server);
        $tickets = $this->ticketRepository->getTicketsBy(
            't.server_port',
            $server->getPort(),
            $page
        );
        return $this->render('ticket/index.html.twig', [
            'pagination' => $tickets,
            'tgdb' => true,
            'server' => $server
        ]);
    }

    #[Route("/tickets/round/{round}/{page}", name: 'round.tickets')]
    public function getTicketsForRound(int $round, int $page = 1): Response
    {
        $tickets = $this->ticketRepository->getTicketsBy(
            't.round_id',
            $round,
            $page
        );
        return $this->render('ticket/index.html.twig', [
            'pagination' => $tickets,
            'tgdb' => true,
            'round' => $round
        ]);
    }

    #[Route("/tickets/player/{ckey}/{page}", name: 'player.tickets')]
    public function getTicketsForCkey(string $ckey, int $page = 1): Response
    {
        $this->denyAccessUnlessGranted('ROLE_BAN');
        $ckey = $this->userRepository->findByCkey($ckey);
        $tickets = $this->ticketRepository->getTicketsByCkey($ckey->getCkey(), $page);
        return $this->render('ticket/index.html.twig', [
            'pagination' => $tickets,
            'tgdb' => true,
            'ckey' => $ckey
        ]);
    }

    #[Route("/ticket/{round}/{ticket}", name: 'ticket')]
    public function getTicket(int $round, int $ticket): Response
    {
        $ticket = $this->ticketRepository->getTicket($round, $ticket);
        $participants = [];
        foreach ($ticket as $t) {
            $participants[] = $t->getSender();
            $participants[] = $t->getRecipient();
        }
        if (!$this->isGranted('ROLE_BAN')) {
            foreach ($ticket as &$t) {
                $t->censor();
            }
        }
        $this->denyAccessUnlessGranted('TICKET_VIEW', $ticket);
        return $this->render('ticket/view.html.twig', [
            'ticket' => $ticket,
            'participants' => array_filter(array_unique($participants))
        ]);
    }
}
