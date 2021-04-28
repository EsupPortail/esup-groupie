<?php

namespace App\Form;

use App\Entity\Membership;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
  
class MembershipType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('memberof', CheckboxType::class, array(
                      'required'  => false,
                      'label' => false)
                     )
                ->add('adminof', CheckboxType::class, array(
                      'required'  => false,
                      'label' => false)
                     );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Membership::class,
        ));
    }

    public function getName()
    {
        return 'membership';
    }
}