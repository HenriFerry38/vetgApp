<?php

namespace App\Controller;
use App\Repository\HoraireRepository;
use App\Entity\Horaire;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[Route('/api/horaire', name: 'app_api_horaire_')]
class HoraireController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private HoraireRepository $repository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    public function new(Request $request): JsonResponse
    {   
        $horaire = $this->serializer->deserialize($request->getContent(), Horaire::class, 'json');
        $horaire->setCreatedAt(new DateTimeImmutable());
       
        $this->manager->persist($horaire);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($horaire, 'json');
        $location = $this->urlGenerator->generate(
            'app_api_horaire_show',
            ['id' => $horaire->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new JsonResponse( $responseData, Response::HTTP_CREATED, ["Location" => $location], true);
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $horaire = $this->repository->findOneBy(['id' => $id]);
        if ($horaire) {
            $responseData = $this->serializer->serialize($horaire, 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id, Request $request): JsonResponse
    {
        $horaire = $this->repository->findOneBy(['id' => $id]);
        if ($horaire) {
            $horaire = $this->serializer->deserialize(
                $request->getContent(),
                Horaire::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $horaire]
            );
            $horaire->setUpdatedAt(new DateTimeImmutable());
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $horaire = $this->repository->findOneBy(['id' => $id]);
        if ($horaire) {
            $this->manager->remove($horaire);
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }
        
        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }
}

