<?php
namespace App\EventListener;

use App\Entity\Service;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class VerificationServiceListener
{
    private $security;
    private $router;
    private $session;

    public function __construct(Security $security, UrlGeneratorInterface $router, SessionInterface $session)
    {
        $this->security = $security;
        $this->router = $router;
        $this->session = $session;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        if ($this->security->getUser() instanceof Service) {
            $service = $this->security->getUser();
            $routeCourante = $event->getRequest()->getRequestUri();

            // Si l'utilisateur a été supprimé, on le déconnecte et le redirige vers la page de connexion
            $routeConnexion = $this->router->generate('connexion');
            if ($service->getSupprimeLe() !== null && $routeCourante !== $routeConnexion) {
                $this->session->invalidate(1);
                $reponse = new RedirectResponse($this->router->generate('connexion'));
                $event->setResponse($reponse);
                return;
            }

            // Si l'utilisateur doit réinitialiser son mot de passe, on le redirigera toujours vers la page de modification du mot de passe
            // Sauf dans le cas d'une usurpation !
            if (!$this->security->getToken() instanceof SwitchUserToken) {
                $routeModificationMotDePasse = $this->router->generate('modificationdumotdepasse', ['id' => $service->getId()]);
                if ($service->getResetMotdepasse() && $routeCourante !== $routeModificationMotDePasse && $routeCourante !== $routeConnexion) {
                    $reponse = new RedirectResponse($routeModificationMotDePasse);
                    $event->setResponse($reponse);
                    return;
                }
            }
        }
    }
}
