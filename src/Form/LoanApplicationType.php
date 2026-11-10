<?php

namespace App\Form;

use App\Entity\LoanApplication;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class LoanApplicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['include_borrower']) {
            $builder->add('user', EntityType::class, [
                'class' => User::class,
                'mapped' => false,
                'data' => $builder->getData() instanceof LoanApplication && $builder->getData()->getId() !== null
                    ? $builder->getData()->getUser()
                    : null,
                'choice_label' => static fn (User $user): string => sprintf('%s (@%s)', trim($user->getFirstName() . ' ' . $user->getLastName()), $user->getUsername()),
                'query_builder' => static fn (UserRepository $users) => $users->createQueryBuilder('user')
                    ->andWhere('user.isAdmin = false')
                    ->andWhere('user.isStaff = false')
                    ->orderBy('user.username', 'ASC'),
                'constraints' => [new Assert\NotNull()],
            ]);
        }

        if ($options['include_staff']) {
            $builder->add('assignedStaff', EntityType::class, [
                'class' => User::class,
                'required' => false,
                'placeholder' => 'Unassigned',
                'choice_label' => static fn (User $user): string => sprintf('%s (@%s)', trim($user->getFirstName() . ' ' . $user->getLastName()), $user->getUsername()),
                'query_builder' => static fn (UserRepository $users) => $users->createQueryBuilder('user')
                    ->andWhere('user.isStaff = true')
                    ->orderBy('user.username', 'ASC'),
            ]);
        }

        $builder
            ->add('loanType', ChoiceType::class, ['choices' => ['Home Loan' => 'home', 'Business Loan' => 'business', 'Personal Loan' => 'personal', 'Asset-Backed Loan' => 'asset_backed'], 'constraints' => [new Assert\NotBlank()]])
            ->add('idType', ChoiceType::class, ['choices' => ['Philippine Passport' => 'philippine_passport', "Driver's License" => 'drivers_license', 'SSS ID' => 'sss_id', 'UMID' => 'umid', 'PhilHealth ID' => 'philhealth_id', 'Postal ID' => 'postal_id', "Voter's ID" => 'voters_id', 'National ID (PhilSys)' => 'philsys_id', 'TIN ID' => 'tin_id'], 'constraints' => [new Assert\NotBlank()]])
            ->add('employmentStatus', ChoiceType::class, ['choices' => ['Employed' => 'employed', 'Self-employed' => 'self_employed'], 'constraints' => [new Assert\NotBlank()]])
            ->add('occupation', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)]])
            ->add('employerName', TextType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 150)]])
            ->add('lengthOfEmployment', ChoiceType::class, ['required' => false, 'placeholder' => 'Select', 'choices' => ['Less than 1 year' => 'less_than_1_year', '1-2 years' => '1_to_2_years', '3-5 years' => '3_to_5_years', '6-10 years' => '6_to_10_years', 'More than 10 years' => 'more_than_10_years']])
            ->add('employerContactNumber', TextType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 20), new Assert\Regex('/^\+?[0-9\s\-()]*$/')]])
            ->add('businessName', TextType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 150)]])
            ->add('businessType', ChoiceType::class, ['required' => false, 'placeholder' => 'Select', 'choices' => ['Sole Proprietorship' => 'sole_proprietorship', 'Retail / Sari-Sari Store' => 'retail_sari_sari_store', 'Food & Beverage' => 'food_and_beverage', 'Services' => 'services', 'Online Business' => 'online_business', 'Other' => 'other']])
            ->add('monthlyIncome', NumberType::class, ['input' => 'string', 'scale' => 2, 'constraints' => [new Assert\Positive(), new Assert\LessThanOrEqual(10000000)]])
            ->add('paymentFrequency', ChoiceType::class, ['choices' => ['Monthly' => 'monthly', 'Bi-weekly' => 'biweekly', 'Weekly' => 'weekly']])
            ->add('amount', NumberType::class, ['input' => 'string', 'scale' => 2, 'constraints' => [new Assert\Range(min: 5000, max: 50000000)]])
            ->add('termMonths', IntegerType::class, ['constraints' => [new Assert\Positive()]])
            ->add('purpose', TextareaType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 500)]]);

        foreach (['validIdPath' => 'Valid ID', 'proofOfIncomePath' => 'Proof of income', 'proofOfAddressPath' => 'Proof of address'] as $name => $label) {
            $constraints = [new Assert\File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png'])];
            if ($options['require_documents']) {
                array_unshift($constraints, new Assert\NotNull());
            }

            $builder->add($name, FileType::class, [
                'mapped' => false,
                'required' => $options['require_documents'],
                'label' => $label,
                'constraints' => $constraints,
            ]);
        }

        $builder->add('employmentCertificatePath', FileType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'Employment certificate',
            'constraints' => [new Assert\File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png'])],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LoanApplication::class,
            'include_borrower' => false,
            'include_staff' => false,
            'require_documents' => false,
        ]);
        $resolver->setAllowedTypes('include_borrower', 'bool');
        $resolver->setAllowedTypes('include_staff', 'bool');
        $resolver->setAllowedTypes('require_documents', 'bool');
    }
}
