<?php
	class DatabaseConnector {
		private $dbConnection = null;

		public function __construct() {
			$host = 'localhost';
			$port = '3306';
			$db   = ''; // Set database name
			$user = ''; // Set username
			$pass = ''; // Set password

			try {
				$this->dbConnection = new \PDO("mysql:host=$host;dbname=$db", $user, $pass);
			} catch (\PDOException $e) {
				exit($e->getMessage());
			}
		}

		public function getConnection()	{
			return $this->dbConnection;
		}
	}
	
	
	
	
	class ChemicalGateway {
		private $db = null;

		public function __construct($db){
			$this->db = $db;
		}

		public function findAll(){
			$statement = "SELECT id, name, risk, precautionary FROM chemicals;";
			
			try {
				$statement = $this->db->query($statement);
				$result = $statement->fetchAll(\PDO::FETCH_ASSOC);
				return $result;
			} catch (\PDOException $e) {
				exit($e->getMessage());
			}
		}

		public function find($cas){
			$statement = "SELECT id, name, risk, precautionary FROM chemicals WHERE id = ?;";
			
			try {
				$statement = $this->db->prepare($statement);
				$statement->execute(array($cas));
				$result = $statement->fetchAll(\PDO::FETCH_NUM);
				return $result;
			} catch (\PDOException $e) {
				exit($e->getMessage());
			}    
		}

		public function insert(Array $input){
			$statement = "INSERT INTO chemicals (id, name, risk, precautionary) VALUES (:id, :name, :risk, :precautionary);";

			try {
				$statement = $this->db->prepare($statement);
				$statement->execute(array(
					'id' => $input['id'],
					'name'  => $input['name'],
					'risk' => $input['risk'] ?? null,
					'precautionary' => $input['precautionary'] ?? null,
				));
				return $statement->rowCount();
			} catch (\PDOException $e) {
				exit($e->getMessage());
			}    
		}

		public function update($cas, Array $input){
			$statement = "UPDATE chemicals SET id = :id, name  = :name, risk = :risk, precautionary = :precautionary WHERE id = :id;";

			try {
				$statement = $this->db->prepare($statement);
				$statement->execute(array(
					'id' => $cas,
					'name'  => $input['name'],
					'risk' => $input['risk'] ?? null,
					'precautionary' => $input['precautionary'] ?? null,
				));
				return $statement->rowCount();
			} catch (\PDOException $e) {
				exit($e->getMessage());
			}    
		}

		public function delete($cas){
			$statement = "DELETE FROM chemicals WHERE id = :id;";

			try {
				$statement = $this->db->prepare($statement);
				$statement->execute(array('id' => $cas));
				return $statement->rowCount();
			} catch (\PDOException $e) {
				exit($e->getMessage());
			}    
		}
	}
	
	
	
	class ChemicalController {
		private $db;
		private $requestMethod;
		private $chemicalId;
		private $ChemicalGateway;

		public function __construct($db, $requestMethod, $chemicalId) {
			$this->db = $db;
			$this->requestMethod = $requestMethod;
			$this->chemicalId = $chemicalId;

			$this->ChemicalGateway = new ChemicalGateway($db);
		}

		private function getAllChemicals() {
			$result = $this->ChemicalGateway->findAll();
			$response['status_code_header'] = 'HTTP/1.1 200 OK';
			$response['body'] = json_encode((object) $result, JSON_PRETTY_PRINT);
			return $response;
		}

		private function getChemical($id) {
			$result = $this->ChemicalGateway->find($id);
			if (! $result) {
				return $this->notFoundResponse();
			}
			$response['status_code_header'] = 'HTTP/1.1 200 OK';
			$response['body'] = json_encode((object) $result, JSON_PRETTY_PRINT);
			return $response;
		}

		private function createChemicalFromRequest() {
			$input = (array) json_decode(file_get_contents('php://input'), TRUE);
			if (! $this->validateChemical($input)) {
				return $this->unprocessableEntityResponse();
			}
			$this->ChemicalGateway->insert($input);
			$response['status_code_header'] = 'HTTP/1.1 201 Created';
			$response['body'] = null;
			return $response;
		}

		private function updateChemicalFromRequest($id)	{
			$result = $this->ChemicalGateway->find($id);
			if (! $result) {
				return $this->notFoundResponse();
			}
			$input = (array) json_decode(file_get_contents('php://input'), TRUE);
			if (! $this->validateChemical($input)) {
				return $this->unprocessableEntityResponse();
			}
			$this->ChemicalGateway->update($id, $input);
			$response['status_code_header'] = 'HTTP/1.1 200 OK';
			$response['body'] = null;
			return $response;
		}

		private function deleteChemical($id) {
			$result = $this->ChemicalGateway->find($id);
			if (! $result) {
				return $this->notFoundResponse();
			}
			$this->ChemicalGateway->delete($id);
			$response['status_code_header'] = 'HTTP/1.1 200 OK';
			$response['body'] = null;
			return $response;
		}

		private function validateChemical($input) {
			if (! isset($input['id'])) {
				return false;
			}
			if (! isset($input['name'])) {
				return false;
			}
			return true;
		}

		private function unprocessableEntityResponse() {
			$response['status_code_header'] = 'HTTP/1.1 422 Unprocessable Entity';
			$response['body'] = json_encode([
				'error' => 'Invalid input'
			]);
			return $response;
		}

		private function notFoundResponse() {
			$response['status_code_header'] = 'HTTP/1.1 404 Not Found';
			$response['body'] = null;
			return $response;
		}
		
		public function processRequest() {
			switch ($this->requestMethod) {
				case 'GET':
					if ($this->chemicalId) {
						$response = $this->getChemical($this->chemicalId);
					} else {
						$response = $this->getAllChemicals();
					};
					break;
				case 'POST':
					$response = $this->createChemicalFromRequest();
					break;
				case 'PUT':
					$response = $this->updateChemicalFromRequest($this->chemicalId);
					break;
				case 'DELETE':
					$response = $this->deleteChemical($this->chemicalId);
					break;
				default:
					$response = $this->notFoundResponse();
					break;
			}
			header($response['status_code_header']);
			if ($response['body']) {
				echo $response['body'];
			}
		}
	}

	header("Access-Control-Allow-Origin: *");
	header("Content-Type: application/json; charset=UTF-8");
	header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
	header("Access-Control-Max-Age: 3600");
	header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

	$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	
	$uri = explode( '/', $uri );
	
	// all of our endpoints start with /chemicals
	// everything else results in a 404 Not Found
	if ($uri[3] !== 'chemicals') {
		header("HTTP/1.1 404 Not Found");
		exit();
	}

	// the cas is, of course, optional and must be a string:
	$casNum = null;
	if (isset($uri[3])) {
		if (isset($uri[4])) {
			$casNum = (string) $uri[4];
		}
	}
	
	$dbConnection = (new DatabaseConnector())->getConnection();
	$requestMethod = $_SERVER["REQUEST_METHOD"];

	// pass the request method and chemical ID to the ChemicalController and process the HTTP request:
	$controller = new ChemicalController($dbConnection, $requestMethod, $casNum);
	$controller->processRequest();
?>