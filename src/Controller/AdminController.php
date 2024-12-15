<?php

namespace Watson\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Watson\Domain\Link;
use Watson\Domain\User;
use Watson\Domain\Tag;
use Watson\Form\Type\LinkType;
use Watson\Form\Type\UserType;

class AdminController {

    /**
     * Admin home page controller.
     *
     * @param Application $app Silex application
     * @param Request $request Incoming request
     */
    public function indexAction(Application $app, Request $request) {
        // Get current page from query parameter, default to 1 if not set
        $page = $request->query->get('page', 1);
                
        // Get paginated links
        $result = $app['dao.link']->findAllPaginated($page);
        $links = $result['links'];
        $numPages = $result['pages'];
        
        // Get all users (no pagination needed for users as there are typically few)
        $users = $app['dao.user']->findAll();
        
        return $app['twig']->render('admin.html.twig', array(
            'links' => $links,
            'users' => $users,
            'currentPage' => $page,
            'numPages' => $numPages
        ));
    }

    /**
     * Add link controller.
     *
     * @param Request $request Incoming request
     * @param Application $app Silex application
     */
    public function addLinkAction(Request $request, Application $app) { 
        $link     = new Link();
        $linkForm = $app['form.factory']->create(new LinkType(), $link);
        $linkForm->handleRequest($request);

        if ($linkForm->isSubmitted() && $linkForm->isValid()) {
            // Store data in object
            $linkData = $linkForm->getData();

            // Transform tags (string) into array of objects "Tag"
            $str_tags = $linkData->getTags();
            $_tags    = array();

            if(!is_null($str_tags) && !empty($str_tags)){
                $array_tags = explode(' ', $str_tags);
                if(count($array_tags)){
                    foreach($array_tags as $row){
                        $word = new Tag();
                        $word->setTitle($row);
                        $app['dao.tag']->save($word);
                        array_push($_tags, $word);
                    }
                }
            }

            $user = $app['user'];
            $link->setUser($user);
            $app['dao.link']->save($link);
            $idLink = $link->getId();

            // Save connection between link and tag(s)
            if(count($_tags)){
                foreach($_tags as $tag){
                    $app['dao.tag']->saveConnection($idLink, $tag);
                }
            }

            $app['session']->getFlashBag()->add('success', 'Le lien a bien été créé.');
        }

        return $app['twig']->render('link_form.html.twig', array(
            'title' => 'Nouveau lien',
            'linkForm' => $linkForm->createView()));
    }

    /**
     * Edit link controller.
     *
     * @param integer $id Link id
     * @param Request $request Incoming request
     * @param Application $app Silex application
     */
    public function editLinkAction($id, Request $request, Application $app) {
        $link     = $app['dao.link']->find($id);
        $linkForm = $app['form.factory']->create(new LinkType(), $link);

        $linkForm->handleRequest($request); // match avant/modif

        // Get tag associated to link
        $str_tags = $link->getTags();

        // If type is array, we need convert to string for show form...
        if(is_array($str_tags)){
            $_tags = $app['dao.tag']->find($id);

            // For convert tag array to tag string 
            if($_tags && count($_tags)){
                $array_tags = [];
                foreach ($_tags as $row) {
                    array_push($array_tags, $row->getTitle());
                }
                $new_value = implode(' ', $array_tags);

                // Update value
                $link->setTags($new_value);
                $linkForm->get('tags')->setData($new_value);
            }
        } 
        
        if ($linkForm->isSubmitted() && $linkForm->isValid()) {
            // ... else it's a string sented by user and we need save it in db
            if(is_string($str_tags) && !empty($str_tags)){
                $array_tags = explode(' ', $str_tags);

                // Remove old connection
                $app['dao.tag']->removeConnecion($id);
                
                if(count($array_tags)){
                    foreach($array_tags as $tag){
                        $word = new Tag();
                        $word->setTitle($tag);
                        $app['dao.tag']->save($word);
                        $app['dao.tag']->saveConnection($id, $word);
                    }
                }
            }

            $app['dao.link']->save($link);
            $app['session']->getFlashBag()->add('success', 'Mise à jour du lien réussie.');
        }
        return $app['twig']->render('link_form.html.twig', array(
            'title' => 'Éditer le lien',
            'linkForm' => $linkForm->createView()));
    }

    /**
     * Delete link controller.
     *
     * @param integer $id Link id
     * @param Application $app Silex application
     */
    public function deleteLinkAction($id, Application $app) {
        // Delete the link
        $app['dao.link']->delete($id);
        $app['session']->getFlashBag()->add('success', 'Le lien a bien été supprimé.');
        
        // Redirect to admin home page
        return $app->redirect($app['url_generator']->generate('admin'));
    }

    /**
     * Add user controller.
     *
     * @param Request $request Incoming request
     * @param Application $app Silex application
     */
    public function addUserAction(Request $request, Application $app) {
        $user     = new User();
        $userForm = $app['form.factory']->create(new UserType(), $user);
        $userForm->handleRequest($request);
        if ($userForm->isSubmitted() && $userForm->isValid()) {
            // Generate a random salt value
            $salt = substr(md5(time()), 0, 23);
            $user->setSalt($salt);
            $plainPassword = $user->getPassword();
           
            // Find the default encoder
            $encoder = $app['security.encoder.digest'];

            // Compute the encoded password
            $password = $encoder->encodePassword($plainPassword, $user->getSalt());
            $user->setPassword($password); 
            $app['dao.user']->save($user);
            $app['session']->getFlashBag()->add('success', 'L\'utilisateur a bien été créé.');
        }

        return $app['twig']->render('user_form.html.twig', array(
            'title' => 'Nouvel utilisateur',
            'userForm' => $userForm->createView()));
    }

    /**
     * Edit user controller.
     *
     * @param integer $id User id
     * @param Request $request Incoming request
     * @param Application $app Silex application
     */
    public function editUserAction($id, Request $request, Application $app) {
        $user     = $app['dao.user']->find($id);
        $userForm = $app['form.factory']->create(new UserType(), $user);
        $userForm->handleRequest($request);
        if ($userForm->isSubmitted() && $userForm->isValid()) {
            $plainPassword = $user->getPassword();
            // Find the encoder for the user
            $encoder = $app['security.encoder_factory']->getEncoder($user);
          
            // Compute the encoded password
            $password = $encoder->encodePassword($plainPassword, $user->getSalt());
            $user->setPassword($password); 
            $app['dao.user']->save($user);
            $app['session']->getFlashBag()->add('success', 'Mise à jour de l\'utilisateur réussie.');
        }
        return $app['twig']->render('user_form.html.twig', array(
            'title' => 'Éditer l\'utilisateur',
            'userForm' => $userForm->createView()));
    }

    /**
     * Delete user controller.
     *
     * @param integer $id User id
     * @param Application $app Silex application
     */
    public function deleteUserAction($id, Application $app) {
        // Delete all associated links
        $app['dao.link']->deleteAllByUser($id);

        // Delete the user
        $app['dao.user']->delete($id);
        $app['session']->getFlashBag()->add('success', 'L\'utilisateur et les liens associés ont été supprimés');

        // Redirect to admin home page
        return $app->redirect($app['url_generator']->generate('admin'));
    }
}
