<?php

namespace App\Security;

use App\Entity\Service;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\PasswordAuthenticatedInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class ServiceFormAuthenticator extends AbstractFormLoginAuthenticator implements PasswordAuthenticatedInterface
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'connexion';

    private $entityManager;
    private $urlGenerator;
    private $csrfTokenManager;
    private $passwordEncoder;

    /**
     * Instancie l'authentificateur en récupérant les objets nécessaires via injection de dépendances
     */
    public function __construct(EntityManagerInterface $entityManager, UrlGeneratorInterface $urlGenerator, CsrfTokenManagerInterface $csrfTokenManager, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
    }


    /**
     * Méthode vérifiant que oui ou non cet authentificateur autorise cette requète pour se connecter
     *  Utilisé par Symfony pour choisir LE bon authentificateur lorsqu'il y en a plusieurs.
     */
    public function supports(Request $request)
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    /**
     * Récupère les données d'authentification à partir du formulaire
     */
    public function getCredentials(Request $request)
    {
        // On récupère les données
        $serviceId = $request->request->get('serviceId');
        $credentials = [
            'serviceId'  => $serviceId,
            'password'   => $request->request->get('password'),
            'csrf_token' => $request->request->get('_csrf_token'),
        ];
        // enregistre l'identifiant du dernier service en session
        $request->getSession()->set(
            Security::LAST_USERNAME,
            $credentials['serviceId']
        );
        return $credentials;
    }

    /**
     * Cette méthode permet de récupérer le service qui tente de se connecter
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        // vérifie si le token CSRF transmis par l'utilisateur est valide
        $token = new CsrfToken('authenticate', $credentials['csrf_token']);
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }
        // récupère le service qui tente de se connecter à partir de son identifiant
        $serviceId = $credentials['serviceId'];

        if (!is_numeric($serviceId) && $serviceId === "invite") {
            return $userProvider->loadUserByUsername($serviceId);
        }

        if (!is_numeric($serviceId) ||
            !($service = $this->entityManager->getRepository(Service::class)->find($serviceId)) ||
            $service->getSupprimeLe() !== null
        ) {
            throw new CustomUserMessageAuthenticationException('Ce service ne semble pas exister !');
        }
        return $service;
    }

    /**
     * On vérifie que le mot de passe récupéré coincide avec le hash stocké en base de données
     */
    public function checkCredentials($credentials, UserInterface $service)
    {
        if ($credentials['serviceId'] === "invite") {
            return true;
        }

        return $this->passwordEncoder->isPasswordValid($service, $credentials['password']);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function getPassword($credentials): ?string
    {
        return $credentials['password'];
    }

    /**
     * Si l'authentification est un succès, cette méthode est utilisée pour rediriger l'utilisateur vers la page voulue
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // Redirige vers la page qui avait été demandée précédemment par l'utilisateur
        if ($targetPath = $this->getTargetPath($request->getSession(), $providerKey)) {
            return new RedirectResponse($targetPath);
        }
        // Sinon redirige vers la page d'accueil
        return new RedirectResponse($this->urlGenerator->generate('accueil'));
    }

    /**
     * Renvoie l'url du formulaire de connexion
     */
    protected function getLoginUrl()
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
