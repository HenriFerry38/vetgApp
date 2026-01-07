<?php

namespace App\Controller;

use App\Repository\MenuRepository;
use App\Repository\RegimeRepository;
use App\Repository\ThemeRepository;
use App\Entity\Menu;
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

#[Route('/api/menu', name: 'app_api_menu_')]
class MenuController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private MenuRepository $repository,
        private RegimeRepository $regimeRepository,
        private ThemeRepository $themeRepository,
        /* private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,*/
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    public function new(): Response
    {   
        $regime = $this->regimeRepository->findOneBy(['libelle' => 'Vegetarien']);
        $theme  = $this->themeRepository->findOneBy(['libelle' => 'Festifs']);
        $menu = new Menu();
        $menu->setTitre('Quai Antique');
        $menu->setNbPersonneMini(2);
        $menu->setPrixParPersonne('15.50');
        $menu->setDescription('Cette qualité et ce goût par le chef Arnaud MICHANT.');
        $menu->setQuantiteRestaurant(10);
        $menu->setRegime($regime); 
        $menu->setTheme($theme);   
        $menu->setCreatedAt(new DateTimeImmutable());

        if (!$regime || !$theme) {
        return $this->json(
        ['error' => 'Regime ou Theme introuvable'],
        Response::HTTP_BAD_REQUEST
        
        );}

        // Tell Doctrine you want to (eventually) save the restaurant (no queries yet)
        $this->manager->persist($menu);
        // Actually executes the queries (i.e. the INSERT query)
        $this->manager->flush();
        return $this->json(
            ['message' => "Menu resource created with {$menu->getId()} id"],
            Response::HTTP_CREATED,
        );
    
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $menu = $this->repository->findOneBy(['id' => $id]);

        if (!$menu) {
            throw $this->createNotFoundException("No Menu found for {$id} id");
        }

        return $this->json(
            ['message' => "A Menu was found : {$menu->getTitre()} for {$menu->getId()} id"]
        );
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id): Response
    {
        $menu = $this->repository->findOneBy(['id' => $id]);

        if (!$menu) {
            throw $this->createNotFoundException("No Menu found for {$id} id");
        }

        $menu->setTitre('Menu name updated');
        $menu->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return $this->redirectToRoute('app_api_menu_show', ['id' => $menu->getId()]);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $menu = $this->repository->findOneBy(['id' => $id]);
        if (!$menu) {
            throw $this->createNotFoundException("No menu found for {$id} id");
        }
        $this->manager->remove($menu);
        $this->manager->flush();
        return $this->json(['message' => "Menu resource deleted"], Response::HTTP_NO_CONTENT);
    }
}
