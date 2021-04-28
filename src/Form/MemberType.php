<?php

namespace App\Form;

use App\Entity\Member;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
  
class MemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('member', CheckboxType::class, array(
                      'required'  => false,
                      'label' => false)
                     )
                ->add('admin', CheckboxType::class, array(
                      'required'  => false,
                      'label' => false)
                     );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Member::class,
        ));
    }

    public function getName()
    {
        return 'member';
    }
}