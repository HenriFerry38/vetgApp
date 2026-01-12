<?php

namespace App\Controller;
use App\Repository\AllergeneRepository;
use App\Entity\Allergene;
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

#[Route('/api/allergene', name: 'app_api_allergene_')]
class AllergeneController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private AllergeneRepository $repository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    public function new(Request $request): JsonResponse
    {   
        $allergene = $this->serializer->deserialize($request->getContent(), Allergene::class, 'json');
        $allergene->setCreatedAt(new DateTimeImmutable());
       
        $this->manager->persist($allergene);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($allergene, 'json');
        $location = $this->urlGenerator->generate(
            'app_api_allergene_show',
            ['id' => $allergene->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new JsonResponse( $responseData, Response::HTTP_CREATED, ["Location" => $location], true);
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $allergene = $this->repository->findOneBy(['id' => $id]);
        if ($allergene) {
            $responseData = $this->serializer->serialize($allergene, 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id, Request $request): JsonResponse
    {
        $allergene = $this->repository->findOneBy(['id' => $id]);
        if ($allergene) {
            $allergene = $this->serializer->deserialize(
                $request->getContent(),
                Allergene::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $allergene]
            );
            $allergene->setUpdatedAt(new DateTimeImmutable());
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $allergene = $this->repository->findOneBy(['id' => $id]);
        if ($allergene) {
            $this->manager->remove($allergene);
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }
        
        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }
}

