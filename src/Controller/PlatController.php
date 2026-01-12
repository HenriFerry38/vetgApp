<?php

namespace App\Controller;
use App\Repository\PlatRepository;
use App\Entity\Plat;
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

#[Route('/api/plat', name: 'app_api_plat_')]
class PlatController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private PlatRepository $repository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    public function new(Request $request): JsonResponse
    {   
        $plat = $this->serializer->deserialize($request->getContent(), Plat::class, 'json');
        $plat->setCreatedAt(new DateTimeImmutable());
       
        $this->manager->persist($plat);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($plat, 'json');
        $location = $this->urlGenerator->generate(
            'app_api_plat_show',
            ['id' => $plat->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new JsonResponse( $responseData, Response::HTTP_CREATED, ["Location" => $location], true);
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $plat = $this->repository->findOneBy(['id' => $id]);
        if ($plat) {
            $responseData = $this->serializer->serialize($plat, 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id, Request $request): JsonResponse
    {
        $plat = $this->repository->findOneBy(['id' => $id]);
        if ($plat) {
            $plat = $this->serializer->deserialize(
                $request->getContent(),
                Plat::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $plat]
            );
            $plat->setUpdatedAt(new DateTimeImmutable());
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $plat = $this->repository->findOneBy(['id' => $id]);
        if ($plat) {
            $this->manager->remove($plat);
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }
        
        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }
}