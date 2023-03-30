<?php
/*
 * Copyright 2022, ESUP-Portail  http://www.esup-portail.org/
 *  Licensed under APACHE2
 *  @author  Peggy FERNANDEZ BLANCO <peggy.fernandez-blanco@univ-amu.fr>
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 */

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class UserEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
                
        $builder
                 ->add('memberships', CollectionType::class, array('entry_type' => MembershipType::class)
                 );

    }
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
                                     'data_class' => User::class,
                                     'attr' => ['id' => 'useredit']
                                     ));
    }
    public function getName()
    {
        return 'useredit';
    }
}