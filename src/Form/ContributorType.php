<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\TaskList;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Lets the user pick a new contributor for the task list passed as "list" option.
 */
final class ContributorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var TaskList $taskList */
        $taskList = $options['list'];

        $builder->add('contributor', EntityType::class, [
            'class' => User::class,
            'choice_label' => 'email',
            'placeholder' => 'Choose a user',
            'query_builder' => static function (EntityRepository $repository) use ($taskList): QueryBuilder {
                $members = [$taskList->getOwner()->getEmail()];
                foreach ($taskList->getContributors() as $contributor) {
                    $members[] = $contributor->getEmail();
                }

                return $repository->createQueryBuilder('contributor')
                    ->where('contributor.email NOT IN (:members)')
                    ->setParameter('members', $members)
                    ->orderBy('contributor.email', \SortDirection::Ascending);
            },
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('list')
            ->setAllowedTypes('list', TaskList::class);
    }
}
