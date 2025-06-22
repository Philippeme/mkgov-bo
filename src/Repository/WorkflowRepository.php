<?php

namespace App\Repository;

use App\Entity\Workflow;
use App\Entity\Procedure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Workflow>
 */
class WorkflowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Workflow::class);
    }

    /**
     * Récupère tous les workflows actifs triés par procédure et ordre
     */
    public function findActiveWorkflows(): array
    {
        return $this->createQueryBuilder('w')
            ->leftJoin('w.procedure', 'p')
            ->addSelect('p')
            ->andWhere('w.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.pname', 'ASC')
            ->addOrderBy('w.stepOrder', 'ASC')
            ->addOrderBy('w.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les workflows par procédure
     */
    public function findByProcedure(Procedure $procedure): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.procedure = :procedure')
            ->andWhere('w.isActive = :active')
            ->setParameter('procedure', $procedure)
            ->setParameter('active', true)
            ->orderBy('w.stepOrder', 'ASC')
            ->addOrderBy('w.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les workflows requis par procédure
     */
    public function findRequiredByProcedure(Procedure $procedure): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.procedure = :procedure')
            ->andWhere('w.isActive = :active')
            ->andWhere('w.isRequired = :required')
            ->setParameter('procedure', $procedure)
            ->setParameter('active', true)
            ->setParameter('required', true)
            ->orderBy('w.stepOrder', 'ASC')
            ->addOrderBy('w.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les workflows optionnels par procédure
     */
    public function findOptionalByProcedure(Procedure $procedure): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.procedure = :procedure')
            ->andWhere('w.isActive = :active')
            ->andWhere('w.isRequired = :required')
            ->setParameter('procedure', $procedure)
            ->setParameter('active', true)
            ->setParameter('required', false)
            ->orderBy('w.stepOrder', 'ASC')
            ->addOrderBy('w.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le prochain ordre de step pour une procédure
     */
    public function getNextStepOrderForProcedure(Procedure $procedure): int
    {
        $result = $this->createQueryBuilder('w')
            ->select('MAX(w.stepOrder)')
            ->andWhere('w.procedure = :procedure')
            ->setParameter('procedure', $procedure)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? $result + 1 : 1;
    }

    /**
     * Compte les workflows par procédure
     */
    public function countByProcedure(Procedure $procedure): int
    {
        return $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.procedure = :procedure')
            ->andWhere('w.isActive = :active')
            ->setParameter('procedure', $procedure)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les workflows par input type
     */
    public function findByInputType(string $inputType): array
    {
        return $this->createQueryBuilder('w')
            ->leftJoin('w.procedure', 'p')
            ->addSelect('p')
            ->andWhere('JSON_CONTAINS(w.inputs, :inputType) = 1')
            ->andWhere('w.isActive = :active')
            ->setParameter('inputType', json_encode($inputType))
            ->setParameter('active', true)
            ->orderBy('p.pname', 'ASC')
            ->addOrderBy('w.stepOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les workflows par output type
     */
    public function findByOutputType(string $outputType): array
    {
        return $this->createQueryBuilder('w')
            ->leftJoin('w.procedure', 'p')
            ->addSelect('p')
            ->andWhere('w.output = :outputType')
            ->andWhere('w.isActive = :active')
            ->setParameter('outputType', $outputType)
            ->setParameter('active', true)
            ->orderBy('p.pname', 'ASC')
            ->addOrderBy('w.stepOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche workflows par nom ou description
     */
    public function searchWorkflows(string $searchTerm): array
    {
        return $this->createQueryBuilder('w')
            ->leftJoin('w.procedure', 'p')
            ->addSelect('p')
            ->andWhere('w.isActive = :active')
            ->andWhere('w.name LIKE :search OR w.shortDescription LIKE :search OR p.pname LIKE :search')
            ->setParameter('active', true)
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('p.pname', 'ASC')
            ->addOrderBy('w.stepOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des workflows
     */
    public function getWorkflowStats(): array
    {
        $totalWorkflows = $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->where('w.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $requiredWorkflows = $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->where('w.isActive = :active')
            ->andWhere('w.isRequired = :required')
            ->setParameter('active', true)
            ->setParameter('required', true)
            ->getQuery()
            ->getSingleScalarResult();

        $proceduresWithWorkflows = $this->createQueryBuilder('w')
            ->select('COUNT(DISTINCT w.procedure)')
            ->where('w.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => (int)$totalWorkflows,
            'required' => (int)$requiredWorkflows,
            'optional' => (int)$totalWorkflows - (int)$requiredWorkflows,
            'procedures_with_workflows' => (int)$proceduresWithWorkflows,
            'average_steps_per_procedure' => $proceduresWithWorkflows > 0 ? round($totalWorkflows / $proceduresWithWorkflows, 2) : 0
        ];
    }

    /**
     * Récupère les inputs les plus utilisés
     */
    public function getMostUsedInputs(): array
    {
        $workflows = $this->createQueryBuilder('w')
            ->select('w.inputs')
            ->where('w.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        $inputCounts = [];
        foreach ($workflows as $workflow) {
            foreach ($workflow['inputs'] as $input) {
                $inputCounts[$input] = ($inputCounts[$input] ?? 0) + 1;
            }
        }

        arsort($inputCounts);
        return array_slice($inputCounts, 0, 10, true);
    }

    /**
     * Récupère les outputs les plus utilisés
     */
    public function getMostUsedOutputs(): array
    {
        $result = $this->createQueryBuilder('w')
            ->select('w.output, COUNT(w.id) as count')
            ->where('w.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('w.output')
            ->orderBy('count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $outputs = [];
        foreach ($result as $row) {
            $outputs[$row['output']] = (int)$row['count'];
        }

        return $outputs;
    }

    /**
     * Trouve les workflows avec des inputs spécifiques
     */
    public function findWithMultipleInputs(array $inputs): array
    {
        $qb = $this->createQueryBuilder('w')
            ->leftJoin('w.procedure', 'p')
            ->addSelect('p')
            ->where('w.isActive = :active')
            ->setParameter('active', true);

        foreach ($inputs as $i => $input) {
            $qb->andWhere("JSON_CONTAINS(w.inputs, :input{$i}) = 1")
               ->setParameter("input{$i}", json_encode($input));
        }

        return $qb->orderBy('p.pname', 'ASC')
                  ->addOrderBy('w.stepOrder', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Récupère les workflows récents
     */
    public function findRecentWorkflows(int $limit = 10): array
    {
        return $this->createQueryBuilder('w')
            ->leftJoin('w.procedure', 'p')
            ->addSelect('p')
            ->andWhere('w.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('w.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie l'ordre des steps pour une procédure
     */
    public function validateStepOrder(Procedure $procedure): bool
    {
        $workflows = $this->findByProcedure($procedure);
        $expectedOrder = 1;

        foreach ($workflows as $workflow) {
            if ($workflow->getStepOrder() !== $expectedOrder) {
                return false;
            }
            $expectedOrder++;
        }

        return true;
    }

    /**
     * Réorganise l'ordre des steps pour une procédure
     */
    public function reorderSteps(Procedure $procedure): void
    {
        $workflows = $this->findByProcedure($procedure);
        $order = 1;

        foreach ($workflows as $workflow) {
            $workflow->setStepOrder($order);
            $order++;
        }

        $this->getEntityManager()->flush();
    }

    /**
     * Dupliquer les workflows d'une procédure vers une autre
     */
    public function duplicateWorkflowsFromProcedure(Procedure $source, Procedure $target): void
    {
        $sourceWorkflows = $this->findByProcedure($source);
        
        foreach ($sourceWorkflows as $sourceWorkflow) {
            $newWorkflow = new Workflow();
            $newWorkflow->setName($sourceWorkflow->getName());
            $newWorkflow->setShortDescription($sourceWorkflow->getShortDescription());
            $newWorkflow->setInputs($sourceWorkflow->getInputs());
            $newWorkflow->setOutput($sourceWorkflow->getOutput());
            $newWorkflow->setProcedure($target);
            $newWorkflow->setStepOrder($sourceWorkflow->getStepOrder());
            $newWorkflow->setDisplayOrder($sourceWorkflow->getDisplayOrder());
            $newWorkflow->setIsRequired($sourceWorkflow->isRequired());
            
            $this->getEntityManager()->persist($newWorkflow);
        }
        
        $this->getEntityManager()->flush();
    }
}