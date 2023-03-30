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

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

class AccueilController extends AbstractController
{
    /**
     * @Route("/", name="homepage")
     */
    public function index()
    {
        if($this->get('security.authorization_checker')->isGranted('ROLE_GESTIONNAIRE')) {
            // Pour les gestionnaires de groupes, on affiche les groupes qu'ils gèrent
            return $this->redirectToRoute('my_groups');
        }
        if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            // Pour les autres, on affiche les groupes dont ils sont membres
            return $this->redirectToRoute('memberships');
        }
    }

    /**
     * @Route("/search", name="search")
     */
    public function search()
    {
        return $this->render('accueil_recherche.html.twig');
    }

    /**
     * @Route("/private", name="private")
     */
    public function private()
    {
        return $this->render('accueil_groupes_prives.html.twig');
    }

    /**
     * @Route("/gestion", name="gestion")
     */
    public function gestion()
    {
        return $this->render('accueil_gestion_groupes.html.twig');
    }
}
