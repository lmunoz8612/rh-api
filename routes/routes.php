<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);
session_start();

spl_autoload_register(function ($className) {
    $controllerPath = __DIR__ . '/../controllers/' . $className . '.php';
    if (file_exists($controllerPath)) {
        require_once $controllerPath;
    }
});
require_once '../utils/response.php';

$method = $_SERVER['REQUEST_METHOD'];
$requestUriParts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
$body = json_decode(file_get_contents('php://input'), true);
$params = $_GET;

$main = $requestUriParts[0] ?? '';
$route = $requestUriParts[1] ?? null;
$subroutes = array_slice($requestUriParts, 2);

if (str_starts_with($route, 'login')) {
    login($method, $subroutes, $body);
}

if (str_starts_with($route, 'logout')) {
    logout($method);
}

$tokenController = new TokenController();
$validateToken = $tokenController->validate();
if (!isset($validateToken['ok'])) {
    unAuthorized();
}

switch ($route) {
    case 'role':
        role($method, $subroutes, $body);
        break;
    case 'policies':
        policies($method, $subroutes, $body);
        break;
    case 'user_policies':
        user_policies($method, $subroutes, $body);
        break;
    case 'user_files':
        user_files($method, $subroutes, $body);
        break;
    case 'users':
        users($method, $subroutes, $body);
        break;
    case 'user':
        user($method, $subroutes, $body);
        break;
    case 'temperature':
        temperature($method);
        break;
    case 'catalog':
        catalog($method, $subroutes, $body);
        break;
    case 'job_positions':
        job_positions($method, $subroutes, $body);
        break;
    case 'job_position':
        job_position($method, $subroutes, $body);
        break;
    case 'organization':
        organization($method);
        break;
    case 'communication':
        communication($method, $subroutes, $body);
        break;
    default:
        pathNotFound();
        break;
}

function login($method, $subroutes, $body) {
    $loginController = new LoginController();
    switch ($method) {
        case 'POST':
            if (count($subroutes) > 0) {
                switch ($subroutes[0]) {
                    case 'password_recovery':
                        $loginController->passwordRecovery($body['username']);
                        break;
                    case 'password_recovery':
                        $loginController->passwordUpdate($body['token'], $body['newPassword'], $body['confirmPassword']);
                        break;
                    default:
                        pathNotFound();
                        break;
                }
            }
            
            if (isset($body['username']) && isset($body['password'])) {
                $loginController->validate($body['username'], $body['password'], $body['rememberMe']);
            }

            internalServerError('No se recibió un usuario y/o contraseña.');
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function role($method, $subroutes, $body) {
    $roleController = new RoleController();
    switch ($method) {
        case 'GET':
            if (count($subroutes) > 0) {
                switch ($subroutes[0]) {
                    case 'catalog':
                        $roleController->getAll();
                        break;
                    default:
                        pathNotFound();
                        break;
                }
            }
            
            $roleController->getBySession();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function users($method, $subroutes, $body) {
    $usersController = new UsersController();
    switch ($method) {
        case 'GET':
            $usersController->getAll();
            pathNotFound();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function user($method, $subroutes, $body) {
    $userController = new UserController();
    switch ($method) {
        case 'GET':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    switch ($subroutes[0]) {
                        case 'has_signed_policies':
                            $userController->hasSignedPolicies();
                            break;
                        default:
                            pathNotFound();
                            break;
                    }
                }
            }

            if (isset($params['id'])) {
                $userController->getById($params['id']);
            }
            
            pathNotFound();
            break;
        case 'POST':
            $userController->save($body);
            break;
        case 'PUT':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    switch ($subroutes[0]) {
                        case 'status':
                            $userController->updateStatus($body['id'], $body['status']);
                            break;
                        case is_numeric($subroutes[0]):
                            $userController->update($subroutes[0], $body);
                            break;    
                        default:
                            pathNotFound();
                            break;
                    }
                }
            }

            pathNotFound();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function user_policies($method, $subroutes, $body) {
    $userPoliciesController = new UserPoliciesController();
    switch ($method) {
        case 'GET':
            if (isset($params['user_id']) && isset($params['signed'])) {
                $userPoliciesController->getAll($params['user_id'], $params['signed']);
            }

            $userPoliciesController->getAll();
            break;
        case 'POST':
            $userPoliciesController->save($body);
            break;
        case 'PUT':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    switch ($subroutes[0]) {
                        case 'status':
                            $userPoliciesController->updateStatus($body);
                            break;
                        default:
                            pathNotFound();
                            break;
                    }
                }
            }

            pathNotFound();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function user_files($method, $subroutes, $body) {
    $userFilesController = new UserFilesController();
    switch ($method) {
        case 'GET':
            if (isset($params['user_id']) && isset($params['type_file'])) {
                $userFilesController->getByType($params['user_id'], $params['type_file']);
            }

            pathNotFound();
            break;
        case 'POST':
            $typeFile = $_POST['type_file'] ?? $body['type_file'] ?? null;
            $userId = $_POST['user_id'] ?? $body['user_id'] ?? null;
            
            switch ($typeFile) {
                case UserFiles::TYPE_PROFILE_PICTURE:
                    $userFilesController->upload($userId, UserFiles::TYPE_PROFILE_PICTURE, null);
                    break;
                case UserFiles::TYPE_SIGNATURE:
                    $userFilesController->upload($userId, UserFiles::TYPE_SIGNATURE, (isset($body['file']) ? $body['file'] : null));
                    break;
                default:
                    internalServerError('No se recibió el tipo de imagen a almacenar.');
                    break;
            }
            
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function temperature($method) {
    switch ($method) {
        case 'GET':
            TemperatureController::get($params['latitude'], $params['longitude']);
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function catalog($method, $subroutes, $body) {
    $catalogController = new CatalogController();
    switch ($method) {
        case 'GET':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (isset($subroutes[1])) {
                        if (isset($params['id'])) {
                            $catalogController->getItemById($subroutes[0], $subroutes[1], $params['id']);
                        }

                        $catalogController->getAll($subroutes[0], $subroutes[1], isset($params['available']) ? $params['available'] : null);
                    }

                    internalServerError('No se recibió un nombre de catálogo válido.');
                }
            }

            pathNotFound();
            break;
        case 'POST':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (isset($subroutes[1])) {
                        if (isset($body['description'])) {
                            $catalogController->saveItem($subroutes[0], $subroutes[1], $body);
                        }

                        internalServerError('No se recibió una descripción válida para crear elemento de catálogo.');    
                    }

                    internalServerError('No se recibió un nombre de catálogo válido.');
                }
            }

            pathNotFound();
            break;
        case 'PUT':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (isset($subroutes[1])) {
                        if (isset($subroutes[2])) {
                            if ($subroutes[2] === 'status') {
                                $catalogController->updateItemStatus($subroutes[0], $subroutes[1], $body);
                            }

                            pathNotFound();
                        }
                        else {
                            if (!isset($body['id'])) {
                                $catalogController->updateItem($subroutes[0], $subroutes[1], $body);
                            }

                            internalServerError('No se recibió el id del elemento de catálogo para actualizar.');
                        }
                    }
                    
                    internalServerError('No se recibió un nombre de catálogo válido.');
                }
            }

            pathNotFound();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function job_positions($method, $subroutes, $body) {
    $jobPositionsController = new JobPositionsController();
    switch ($method) {
        case 'GET':
            $jobPositionsController->getAll(isset($params['page']) ? $params['page'] : 1);
            pathNotFound();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function job_position($method, $subroutes, $body) {
    $jobPositionController = new JobPositionController();
    switch ($method) {
        case 'GET':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if ($subroutes[0] === 'positions') {
                        if (isset($params['id'])) {
                            $jobPositionController->getDataById($params['id']);
                        }

                        $jobPositionController->getAll(isset($params['available']) ? $params['available'] : null);
                    }
                }
            }

            pathNotFound();
            break;
        case 'POST':
            $jobPositionController->save($body);
            break;
        case 'PUT':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    if (is_numeric($subroutes[0])) {
                        $jobPositionController->update($subroutes[0], $body);
                    }
                }
            }

            pathNotFound();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function organization($method) {
    $organizationController = new OrganizationController();
    switch ($method) {
        case 'GET':
            $organizationController->getData();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function policies($method, $subroutes, $body) {
    $policiesController = new PoliciesController();
    switch ($method) {
        case 'GET':
            if (isset($params['id'])) {
                if ($subroutes[0] === 'all_users_by_id') {
                    $policiesController->getAllUsersById($params['id'], isset($params['page']) ? $params['page'] : 1);
                }

                $policiesController->getById($params['id']);
            }

            $policiesController->getAll();
            break;
        case 'POST':
            $policiesController->save($body);
            break;
        case 'PUT':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    switch ($subroutes[0]) {
                        case 'status':
                            $policiesController->updateStatus($body['id'], $body['status']);
                            break;
                        case is_numeric($subroutes[0]):
                            $policiesController->update($subroutes[0], $body);
                            break;    
                        default:
                            pathNotFound();
                            break;
                    }
                }
            }

            pathNotFound();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function communication($method, $subroutes, $body) {
    $communicationController = new CommunicationController();
    switch ($method) {
        case 'GET':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    switch ($subroutes[0]) {
                        case 'dashboard':
                            $communicationController->dashboard();
                            break;
                        case 'post':
                            if (isset($params['id'])) {
                                $communicationController->getPostById($params['id']);
                            }
                            break;
                        case 'posts':
                            $communicationController->getAllPosts();
                            break;
                        case 'post_types':
                            $communicationController->getAllPostTypes();
                            break;
                        default:
                            pathNotFound();
                            break;
                    }
                }
            }

            pathNotFound();
            break;
        case 'POST':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    switch ($subroutes[0]) {
                        case 'post':
                            $communicationController->savePost($body);
                            break;
                        case 'post_file':
                            $communicationController->uploadPostFile($_POST);
                            break;
                        case 'birthday_reaction':
                            $communicationController->addBirthdayReaction($body);
                            break;
                        case 'remove_birthday_reaction':
                            $communicationController->removeBirthdayReaction($body);
                            break;
                        case 'birthday_comment':
                            $communicationController->addBirthdayComment($body);
                            break;
                        default:
                            pathNotFound();
                            break;
                    }
                }
            }

            pathNotFound();
            break;
        case 'PUT':
            if (count($subroutes) > 0) {
                if (isset($subroutes[0])) {
                    switch ($subroutes[0]) {
                        case 'post':
                            if (isset($subroutes[1])) {
                                if (is_numeric($subroutes[1])) {
                                    $communicationController->updatePost($subroutes[1], $body);
                                }
                            }
                            break;
                        case 'post_status':
                            $communicationController->updatePostStatus($body);
                            break;
                        default:
                            break;
                    }
                }
            }

            pathNotFound();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function logout($method) {
    switch ($method) {
        case 'POST':
            LogoutController::logout();
            break;
        default:
            methodNotAllowed();
            break;
    }
}

function pathNotFound() {
    handleError(404, 'Ruta no encontrada.');
    exit();
}

function methodNotAllowed() {
    handleError(405, 'Método no permitido.');
    exit();
}

function unAuthorized() {
    header('HTTP/1.1 401 Unauthorized');
    http_response_code(401);
    echo json_encode(['error' => true, 'message' => 'Error: No autorizado.']);
    exit();
}

function internalServerError($message = null) {
    handleError(500, $message ?? 'Error interno de servidor.');
    exit();
}
?>
