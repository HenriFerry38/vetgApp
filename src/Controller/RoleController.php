<?php

namespace App\Controller;
use App\Repository\RoleRepository;
use App\Entity\Role;
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

#[Route('/api/role', name: 'app_api_role_')]
class RoleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private RoleRepository $repository,
        /* private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,*/
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    public function new(): Response
    {   
        $role = new Role();
        $role->setLibelle('ROLE_USER');
        $role->setCreatedAt(new DateTimeImmutable());
       
        $this->manager->persist($role);
        
        $this->manager->flush();
        return $this->json(
            ['message' => "Role resource created with {$role->getId()} id"],
            Response::HTTP_CREATED,
        );
    
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $role = $this->repository->findOneBy(['id' => $id]);

        if (!$role) {
            throw $this->createNotFoundException("No Role found for {$id} id");
        }

        return $this->json(
            ['message' => "A Role was found : {$role->getLibelle()} for {$role->getId()} id"]
        );
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id): Response
    {
        $role = $this->repository->findOneBy(['id' => $id]);

        if (!$role) {
            throw $this->createNotFoundException("No role found for {$id} id");
        }

        $role->setLibelle('role name updated');
        $role->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return $this->redirectToRoute('app_api_role_show', ['id' => $role->getId()]);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $role = $this->repository->findOneBy(['id' => $id]);
        if (!$role) {
            throw $this->createNotFoundException("No role found for {$id} id");
        }
        $this->manager->remove($role);
        $this->manager->flush();
        return $this->json(['message' => "Role resource deleted"], Response::HTTP_NO_CONTENT);
    }
}

