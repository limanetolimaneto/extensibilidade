<?php
// CONFIG ============================================
    // Para salvar dados em arquivo JSON
    putenv("DB=JSON");
    putenv("DB_NAME=docs.json");

    // Para salvar dados em arquivo MySQL
    // putenv("DB=MYSQL");
    // putenv("DB_NAME=extensibilidade");
    // putenv("DB_HOST=localhost");
    // putenv("DB_USER=user");
    // putenv("DB_PASSWORD=pass");
// CONFIG ============================================

// INTERFACE =========================================

    interface DocumentManager{

        public function create(array $readline):void;

        public function list(array $readline):void;
    
    }    

    interface DatabaseManager{

        public function lastId(string $table):int;

        public function save(string $table, array $data):void;

        public function updateStatus(string $table, array $data):void;

        public function list(string $table, bool $status):array;

    }
// INTERFACE =========================================

// ABSTRACT CLASSES ==================================
    abstract class BaseService{
        protected string $tableName;

        public function __construct(protected Connection $data) {}

        public function list( $status ){
            var_dump($this->data->list($this->tableName, $status));
        }
    
    }
// ABSTRACT CLASSES ==================================

// DATABASE CLASSES ==================================
    
    class JSON implements DatabaseManager{
        private $documents;

        public function __construct(){
            
            if(!file_exists('docs.json')){
                file_put_contents(getenv('DB_NAME'),json_encode(["documents" => [
                                                                        "freight" => [],
                                                                        "blrt" => [],
                                                                        "manifest" => [],
                                                                        "insurance" => []
                                                                    ]],JSON_PRETTY_PRINT));
            }

            $this->documents = json_decode(file_get_contents(getenv("DB_NAME")),true);
        }

        public function lastId(string $table):int{
            $ids = array_column($this->documents['documents'][$table],"id");
            return count($ids) == 0 ? 0 : (int) max($ids);
        }

        public function save(string $table, array $data):void{
            $merged = array_merge($data, $this->documents['documents'][$table]);
            usort($merged, function($a, $b) {
                return $a['id'] <=> $b['id'];
            }); 
            $this->documents['documents'][$table] = $merged;
            file_put_contents(getenv("DB_NAME"),json_encode($this->documents, JSON_PRETTY_PRINT));

        }

        public function updateStatus(string $table, array $data):void{
            foreach ($this->documents['documents'][$table] as &$item) {
                if( in_array($item['id'],$data) ){
                    $item['status'] = true;
                }
            };
        }

        public function list(string $table, bool $status):array{
            $list = array_filter($this->documents['documents'][$table],fn($item) => $item['status'] == $status);
            return $list;
        }

    }

    class Connection {
        private string $db;
        private string $dbName;
        private DatabaseManager $resolvedClass;

        public function __construct(private Container $container){
            $this->db = getenv("DB");
            $this->dbName = getenv("DB_NAME");
            $class = match ($this->db) {
                        'JSON' => JSON::class,
                        'MYSQL' => MYSQL::class,
                    };
            $this->resolvedClass = $this->container->resolve($class);
        }

        public function lastId($table){
            return $this->resolvedClass->lastId($table);
        }

        public function save($table,$array){
            $this->resolvedClass->save($table,$array);
        }

        public function updateStatus($table,$array){
            $this->resolvedClass->updateStatus($table,$array);
        }

        public function list($table,$status){
            return $this->resolvedClass->list($table,$status);
        }

       
    }
   
// DATABASE CLASSES ==================================

// SERVICE CLASS =====================================
    class FreightService extends BaseService{
        protected string $tableName = 'freight';

        public function __construct(protected Connection $data){
            parent::__construct($data);
        }

        public function create($readline){
            $array = [];
            $towns = ["Stelenbosch","Somerset West", "Mitchells Plain","Cape Town"];
            $customers = ["Jorge","Steven", "Wakalo","Anthony", "Mikaela"];
            $id = $this->data->lastId($this->tableName) + 1;
            for ($i=0; $i < $readline[2]; $i++) { 
                $array [] = [
                    "id" => $id + $i,
                    "date" => date('d-M'),
                    "origin" => "paarl",
                    "destination" => $towns[rand(0,3)],
                    "customer" => $customers[rand(0,4)],
                    "weight" =>  rand(10,2000),
                    "status" => false,
                ];
            }
            $this->data->save($this->tableName,$array);
        }
        
    }

    class BlrtService extends BaseService{
        protected string $tableName = 'blrt';

        public function __construct(protected Connection $data){
            parent::__construct($data);
        }

        public function generate($readline){
            $freights = $this->data->list('freight',false);
            $blrts = [];
            $id = $this->data->lastId($this->tableName) + 1;
            foreach ($freights as $item) {
                $blrts [] = [
                    "id" => $id,
                    "date" => date("d-M"),
                    "origin" => "Paarl",
                    "destination" => $item["destination"],
                    "customer" => $item["customer"],
                    "weight" => $item["weight"],
                    "amount" => round($item["weight"] * 4,2),
                    "status" => false
                ];
                $id++;
            }
            $this->data->updateStatus('freight',array_column($freights,'id'));
            $this->data->save($this->tableName,$blrts);
        }

    } 

    class ManifestService extends BaseService{
        protected string $tableName = 'manifest';

        public function __construct(protected Connection $data){
            parent::__construct($data);
        }

        public function generate($readline){
            $blrts = $this->data->list('blrt',false);
            $id = $this->data->lastId($this->tableName) + 1;
            $manifest = [
                "id" => $id,
                "date" => date("d-M"),
                "origin" => "Paarl",
                "destination" => "Cape Town",
                "weight_total" => 0,
                "amount_total" => 0,
                "status" => false
            ];
            
            foreach ($blrts as $item) {
                $manifest["weight_total"] += $item["weight"];
                $manifest["amount_total"] += $item["amount"];
            }
            $this->data->updateStatus('blrt',array_column($blrts,'id'));
            $this->data->save($this->tableName,[$manifest]);

        }
    }

    class InsuranceService extends BaseService{
        protected string $tableName = 'insurance';

        public function __construct(protected Connection $data){
            parent::__construct($data);
        }

        public function generate($readline){
            $manifests = $this->data->list('manifest',false);
            $id = $this->data->lastId($this->tableName) + 1;
            $insurance = [
                "id" => $id,
                "date" => date("d-M"),
                "origin" => "Paarl",
                "destination" => "Cape Town",
                "manifests" => [],
                "amount_total" => 0,
                "status" => false
            ];
            
            foreach ($manifests as $item) {
                $insurance["amount_total"] += $item["amount_total"];
                array_push($insurance['manifests'],$item["id"]);
            }
            $this->data->updateStatus('manifest',array_column($manifests,'id'));
            $this->data->save($this->tableName,[$insurance]);

        }
    }
// SERVICE CLASS =====================================

// CONTRACT CLASSES ==================================
    // As classes contract implementam a interface DocumentManager
    //   isso permite delegar aos metodos create e list as regras 
    //   pertinentes a classe istanciada, ou sela, ao documento
    //   informado na entrada de dados.      
    class FreightContract implements DocumentManager{

        public function __construct(private FreightService $service){}

        public function create(array $readline):void{
            $this->service->create($readline);
        }

        public function list(array $readline):void{
            $this->service->list(  filter_var($readline[3], FILTER_VALIDATE_BOOLEAN) );
        }
    }    

    class BlrtContract implements  DocumentManager{
        
        public function __construct(private BlrtService $service){}

        public function create(array $readline):void{
            $this->service->generate($readline);
        }

        public function list(array $readline):void{
            $this->service->list(  filter_var($readline[3], FILTER_VALIDATE_BOOLEAN) );
        }
    }

    class ManifestContract implements DocumentManager{
        public function __construct(private ManifestService $service){}

        public function create (array $readline):void{
            $this->service->generate($readline);
        }

        public function list(array $readline):void{
            $this->service->list(  filter_var($readline[3], FILTER_VALIDATE_BOOLEAN) );
        }
    }

    class InsuranceContract implements DocumentManager{
        public function __construct(private InsuranceService $service){}

        public function create (array $readline):void{
            $this->service->generate($readline);
        }

        public function list(array $readline):void{
            $this->service->list(  filter_var($readline[3], FILTER_VALIDATE_BOOLEAN) );
        }
    }
// CONTRACT CLASSES ==================================

// DEPENDENCY INJECTION CLASS ========================
    class Container {
        private array $instances = [];
        
        public function resolve($className){
            if (isset($this->instances[$className])) {
                return $this->instances[$className];
            };

            $reflectionClass = new ReflectionClass($className);
            $construct = $reflectionClass->getConstructor();
            if ($construct == null) {
                return new $className();
            }

            $parameteres = $construct->getParameters();
            $params = [];
            foreach ($parameteres as $p) {
                $type = $p->getType();
                if ($type === null) {
                    throw new Exception("Cannot resolve parameter without type");
                }
                $name = $type->getName();
                $params [] = $this->resolve($name);
            }
            $instance = $reflectionClass->newInstanceArgs($params);
            $this->instances[$className] = $instance;
            return $instance;
        }
    }
// DEPENDENCY INJECTION CLASS ========================

// CONTROLLERS =======================================

    class LadingController{

        public function __construct(private Container $container){} 

        public function matchClass(string $code):string{
            $class = match ($code) {
                '1' => FreightContract::class,    
                '2' => BlrtContract::class,
                '3' => ManifestContract::class,
                '4' => InsuranceContract::class,
            };
            return $class;
        }    

        public function createGenerateListDocument(array $readline):void{
            $resolvedClass = $this->container->resolve($this->matchClass($readline[1]));
            match ($readline[0]) {
                '1' => $this->createGenerateDocument($resolvedClass,$readline),
                '2' => $this->listDocument($resolvedClass,$readline),
            };
        }

        public function createGenerateDocument(DocumentManager $docManager, array $readline):void{
            $docManager->create($readline);
        }

        public function listDocument(DocumentManager $docManager, array $readline):void{
            $docManager->list($readline);
        }


        // public function generate($readline){
        //     [$code,$qttStt] = explode(",", $readline);
        //     $resolved = $this->container->resolve($this->matchClass($code));
        //     $this->generateDocument($resolved, $qttStt);
        // }

        // public function list($readline){
        //     [$code,$stt] = explode(",", $readline);
        //     $resolved = $this->container->resolve($this->matchClass($code));
        //     $this->listDocument($resolved, $stt);
        // }

        // public function generateDocument(DocumentGenerator $strategy, $qttStt){
        //     $strategy->create($qttStt);
        // }  

        // public function listDocument(DocumentGenerator $strategy, $stt){
        //     $strategy->list($stt);
        // }

    }

// CONTROLLERS =======================================

// INPUT =============================================

    // Esta funcao implementa:
        //  entrada de dados;
        //  validacao os valores recebidos;
        //  formatacao da variavel recebida pelo objeto da classe LadingController
    // Esta funcao implementa:
    function inputValidate(){
        $action = 0;
        $document = 0;
        $quantity = 0;
        $status = 'false';
        
        // Action input 
            //  1 -> Create/generate a document
            //  2 -> list documents filtered by status (false or true)
        // Action input 
        $input = 0;
        while($input != 1 && $input != 2 && $input != 'x'){
            system('clear');
            echo " ________________________________\n";
            echo "| CODE        ACTION             |\n";
            echo "|                                |\n";
            echo "|  1          CREATE/GENERATE    |\n";
            echo "|  2          LIST               |\n";
            echo "|                                |\n";
            echo "|  x          EXIT               |\n";
            echo "|________________________________|\n";
            $input = readline("ENTER: ");
            if($input == 'x'){return;}
        }
        system('clear');
        $action = $input;

        // Document input
            //  1 -> Freights
            //  2 -> BLRTs
            //  3 -> Manifests
            //  4 -> Insurance 
        // Document input
        $input = 0;
        while($input != 1 && $input != 2 && $input != 3 && $input != 4 && $input != 'x'){
            system('clear');
            echo " ________________________________\n";
            echo "| CODE        DOCUMENT           |\n";
            echo "|                                |\n";
            echo "|  1          FREIGHT            |\n";
            echo "|  2          BLRT               |\n";
            echo "|  3          MANIFEST           |\n";
            echo "|  4          INSURANCE          |\n";
            echo "|                                |\n";
            echo "|  x          EXIT               |\n";
            echo "|________________________________|\n";
            $input = readline("ENTER: ");
            if($input == 'x'){return;}
        }
        system('clear');
        $document = $input;

        // Quantity input
            //  Used only for creating new freights
        // Quantity input
        $input = 0;
        if($action == 1 && $document == 1){
            while ( $input != 'x' && (int)$input <= 0 ) {
                system('clear');
                echo " ________________________________\n";
                echo "|                                |\n";
                echo "| NUMBER > 0  FREIGHT'S QUANTITY |\n";
                echo "|                                |\n";
                echo "|  x           EXIT              |\n";
                echo "|________________________________|\n";
                $input = readline("ENTER:");
                if($input == 'x'){return;}
            }
            system('clear');
            $quantity = $input;
        }
       
        // Status input
            //  Used for filtering documents when the choosen action is 2 (list)
            //  Freight's status   => false = there's no BLRT generated based on it
            //                     => true  = there's a BLRT generated based on it
            //  BLRT's status      => false = it ins't listed in a manifest document
            //                     => true  = it is listed in a manifest document
            //  Manifest's status  => false = it ins't listed in a Insurance document
            //                     => true  = it is listed in a Insurance document
        // Status input
        $input = 0;
        if($action == 2){
            while ( $input != 'x' && ( strcasecmp($input, 'false') != 0 && strcasecmp($input, 'true') != 0) ) {
                system('clear');
                echo " ________________________________\n";
                echo "|                                |\n";
                echo "| TRUE/FALSE  DOCUMENT'S STATUS  |\n";
                echo "|                                |\n";
                echo "|  x            EXIT             |\n";
                echo "|________________________________|\n";
                $input = readline("ENTER:");
                if($input == 'x'){return;}
            }
            system('clear');
            $status = $input;
        }
        
        return [$action,$document,$quantity,$status];

    }

    $readline = inputValidate();

    $ladingController = new LadingController(new Container());
    $ladingController->createGenerateListDocument($readline);
 
// INPUT =============================================

