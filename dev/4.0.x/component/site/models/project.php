<?php
/**
* @package   Projectfork
* @copyright Copyright (C) 2006-2012 Tobias Kuhn. All rights reserved.
* @license   http://www.gnu.org/licenses/gpl.html GNU/GPL, see LICENSE.txt
*
* This file is part of Projectfork.
*
* Projectfork is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
*
* Projectfork is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Projectfork. If not, see <http://www.gnu.org/licenses/gpl.html>.
**/

defined( '_JEXEC' ) or die( 'Restricted access' );


// Import Joomla Model Item Class
jimport('joomla.application.component.modelitem');


/**
 * Projectfork Component Project Model
 *
 */
class ProjectforkModelProject extends JModelItem
{
    /**
	 * Model context string.
	 *
	 * @var    string
	 */
	protected $_context = 'com_projectfork.project';


    /**
	 * Method to auto-populate the model state.
	 * Note. Calling getState in this method will result in recursion.
	 *
	 */
    protected function populateState()
	{
		$app = JFactory::getApplication('site');

		// Load state from the request.
		$pk = JRequest::getInt('id');
		$this->setState('project.id', $pk);

        $offset = JRequest::getUInt('limitstart');
		$this->setState('list.offset', $offset);

		// Load the parameters.
		$params = $app->getParams();
		$this->setState('params', $params);
	}


    /**
	 * Method to get project data
	 *
	 * @param    integer    The id of the project
	 * @return	 mixed	    Project data object on success, false on failure.
	 */
	public function &getItem($pk = null)
	{
	    // Initialise variables.
		$pk = (!empty($pk)) ? $pk : (int) $this->getState('project.id');

		if(is_null($this->_item))    $this->_item = array();
        if(isset($this->_item[$pk])) return $this->_item[$pk];

        // Item not in cache - try to fetch it
        try {
            $db = $this->getDbo();
			$query = $db->getQuery(true);

            $query->select(
                $this->getState('item.select',
                    'a.id, a.asset_id, a.title, a.alias, a.description,'
                    . 'a.created, a.created_by, a.modified, a.modified_by, a.checked_out,'
                    . 'a.checked_out_time, a.attribs, a.access, a.state,'
                    . 'a.start_date, a.end_date'
                )
            );
			$query->from('#__pf_projects AS a');

            // Join on user table.
			$query->select('u.name AS author');
			$query->join('LEFT', '#__users AS u on u.id = a.created_by');

            $query->where('a.id = ' . (int) $pk);

            $db->setQuery($query);
			$data = $db->loadObject();


			if($error = $db->getErrorMsg()) throw new Exception($error);
			if(empty($data)) return JError::raiseError(404,JText::_('COM_PROJECTFORK_ERROR_PROJECT_NOT_FOUND'));


            // Convert parameter fields to objects.
			$registry = new JRegistry;
			$registry->loadString($data->attribs);
			$data->params = clone $this->getState('params');
			$data->params->merge($registry);


            // Calculate permission to edit
            $user = JFactory::getUser();

			if (!$user->get('guest')) {
				$userId	= $user->get('id');
				$asset	= 'com_projectfork.project.'.$data->id;

				// Check general edit permission first.
				if ($user->authorise('core.edit', $asset) || $user->authorise('project.edit', $asset)) {
					$data->params->set('access-edit', true);
				}
				// Now check if edit.own is available.
				elseif (!empty($userId) && ($user->authorise('core.edit.own', $asset) || $user->authorise('project.edit.own', $asset))) {
					// Check for a valid user and that they are the owner.
					if ($userId == $data->created_by) {
						$data->params->set('access-edit', true);
					}
				}
			}

			// Compute view access permissions.
			if ($access = $this->getState('filter.access')) {
				// If the access filter has been set, we already know this user can view.
				$data->params->set('access-view', true);
			}
			else {
				// If no access filter is set, the layout takes some responsibility for display of limited information.
				$user   = JFactory::getUser();
				$groups = $user->getAuthorisedViewLevels();

				$data->params->set('access-view', in_array($data->access, $groups));
			}

            $this->_item[$pk] = $data;
        }
        catch (JException $e)
		{
			if ($e->getCode() == 404) {
				// Need to go thru the error handler to allow Redirect to work.
				JError::raiseError(404, $e->getMessage());
			}
			else {
				$this->setError($e);
				$this->_item[$pk] = false;
			}
		}

	    return $this->_item[$pk];
    }
}