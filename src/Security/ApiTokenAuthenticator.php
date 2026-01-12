<?php

namespace App\Security;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(private UserRepository $repository)
    {
    }

    public function supports(Request $request): ?bool
    {
        $path = $request->getPathInfo();

    // Routes publiques: on ne déclenche jamais l'authenticator
    if ($path === '/api/registration' || $path === '/api/login' || str_starts_with($path, '/api/doc')) {
        return false;
    }

    // On ne supporte que si le token est présent ET non vide
    $token = $request->headers->get('X-AUTH-TOKEN');

    return is_string($token) && $token !== '';
    }

    public function authenticate(Request $request): Passport
    {
        $apiToken = $request->headers->get('X-AUTH-TOKEN');
        if (!is_string($apiToken) || $apiToken === '') {
            throw new CustomUserMessageAuthenticationException('Token Api inexistant');
        }

        return new SelfValidatingPassport(
            new UserBadge($apiToken, function (string $apiToken) {
                $user = $this->repository->findOneBy(['apiToken' => $apiToken]);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Token API invalide');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['message' => strtr($exception->getMessageKey(), $exception->getMessageData())],
            Response::HTTP_UNAUTHORIZED
        );
    }

    //    public function start(Request $request, ?AuthenticationException $authException = null): Response
    //    {
    //        /*
    //         * If you would like this class to control what happens when an anonymous user accesses a
    //         * protected page (e.g. redirect to /login), uncomment this method and make this class
    //         * implement Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface.
    //         *
    //         * For more details, see https://symfony.com/doc/current/security/experimental_authenticators.html#configuring-the-authentication-entry-point
    //         */
    //    }
}
