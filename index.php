<?php

// ============================================================
// PERFORMANCE & ARCHITECTURE DEMO 
// ============================================================
/** This project explores the advantages of Inversion of Control 
 *  (IoC) through Dependency Injection and the Reflection API. 
 *  By utilizing interfaces and abstract classes, I aim to 
 *  demonstrate a flexible system architecture while keeping the 
 *  implementation minimal. Note: This is not production-ready 
 *  code; security vulnerabilities may exist as focus is strictly 
 *  on architectural efficiency."
 */



// ============================================================
// ENVIRONMENT CONFIGURATION
// ============================================================
/** This section simulates environment variables (.env).
 *  The system is designed to be decoupled, allowing you to 
 *  switch between JSON and MySQL storage seamlessly.
 */

// Option A: JSON File Storage (Default)
putenv("DB=JSON");
putenv("DB_NAME=docs.json");

// Option B: MySQL Database Storage
// putenv("DB=MYSQL");
// putenv("DB_NAME=extensibilidade");
// putenv("DB_HOST=localhost");
// putenv("DB_USER=user");
// putenv("DB_PASSWORD=pass");



// ============================================================
// DEPENDENCY INJECTION CONTAINER
// ============================================================
/**
 * DI Container using Reflection API.
 * 
 * I know that using Reflection can be slow. To fix this, I 
 * added a cache for the objects. The container only works 
 * hard the first time it creates a class. After that, it just 
 * returns the same instance from memory. 
 * This makes the application much faster.
 */

class Container {
    private array $instances = [];
    
    public function resolve(string $className) {
        // 1. Singleton pattern: Returns existing instance to optimize performance
        if (isset($this->instances[$className])) {
            return $this->instances[$className];
        }

        try {
            $reflectionClass = new ReflectionClass($className);

            // Ensure the class can actually be instantiated
            if (!$reflectionClass->isInstantiable()) {
                throw new Exception("Class {$className} is not instantiable (it might be an Interface or Abstract Class).");
            }

            $constructor = $reflectionClass->getConstructor();

            // 2. Simple instantiation if no constructor exists
            if ($constructor === null) {
                $instance = new $className();
            } else {
                // 3. Recursive Dependency Resolution
                $parameters = $constructor->getParameters();
                $dependencies = [];

                foreach ($parameters as $parameter) {
                    $type = $parameter->getType();

                    if ($type === null || $type->isBuiltin()) {
                        throw new Exception("Cannot resolve parameter '{$parameter->getName()}' in {$className}: Missing type hint.");
                    }

                    // Recursively resolve the dependency
                    $dependencies[] = $this->resolve($type->getName());
                }

                $instance = $reflectionClass->newInstanceArgs($dependencies);
            }

            // Cache the instance for future use
            $this->instances[$className] = $instance;
            return $instance;

        } catch (ReflectionException $e) {
            // Handle cases where the class does not exist
            throw new Exception("Reflection Error: " . $e->getMessage());
        } catch (Exception $e) {
            // Handle architectural or logic errors
            throw new Exception("Container Error: " . $e->getMessage());
        }
    }
}



// ============================================================
// INTERFACES
// ============================================================
/**
 * Interfaces are essential for Dependency Injection. 
 * They define common methods for different classes. 
 * The actual logic lives inside each class that implements 
 * the interface. This allows each class to follow its own 
 * rules for the same method signature.
 */

/**
 * Interface for Document Management operations.
 * These methods are implemented by the following concrete classes:
 * - FreightContract, BlrtContract, ManifestContract, InsuranceContract.
 * 
 * It ensures a consistent API for creating and listing different document types.
 */
interface DocumentManager {

    // Handles the logic for creating a new document record.
    public function create(array $readline): void;

    // Handles the logic for retrieving and displaying a list of documents.
    public function list(array $readline): void;

}

/**
 * Interface for Database Persistence.
 * This contract allows switching between different storage engines:
 * - Json (File-based storage)
 * - Mysql (Relational database)
 */
interface DatabaseManager {

    // Retrieves the last inserted ID for the specified table/file.
    public function lastId(string $table): int;

    // Saves new data into the storage system.
    public function save(string $table, array $data): void;

    // Updates the status of an existing record.
    public function updateStatus(string $table, array $data): void;

    // Fetches a list of records filtered by their status.
    public function list(string $table, bool $status): array;

}



// ============================================================
// ABSTRACT CLASSES
// ============================================================
/**
 * Provides shared functionality and properties for all specific 
 * controllers that I named service classes.
 * It uses a data connection to handle generic operations.
 */
abstract class BaseService{
    // The table name is defined by the child class (Late Static Binding)
    protected string $tableName;

    /**
     * Dependency Injection of the Data Connection.
     */ 
    public function __construct(protected Connection $data) {}

    /**
     * Generic list method.
     * Fetches and displays records from the database based on the status.
     */
    public function list( $status ){
        var_dump($this->data->list($this->tableName, $status));
    }

}



// ============================================================
// DATA PERSISTENCE LAYER (ADAPTERS)
// ============================================================
/** The Persistence Layer handles data storage and retrieval. 
 * It implements the DatabaseManager interface to allow 
 * seamless switching between JSON file storage and MySQL 
 * relational storage.
 *  The Connection class acts as an abstraction layer, 
 * ensuring the rest of the application remains independent 
 * of the specific database engine being used
*/

/**
 * JSON Storage Implementation.
 * Handles data persistence using a local JSON file. 
 * Ideal for small applications or prototypes.
 */
class Json implements DatabaseManager{
    private array $documents;
    private string $filePath;    

    public function __construct() {
        // Fetch the database filename from environment variables
        $this->filePath = getenv("DB_NAME") ?: 'docs.json';

        // Bootstrap: Create the file with a default structure if it doesn't exist
        if (!file_exists($this->filePath)) {
            $defaultSchema = [
                "documents" => [
                    "freight" => [],
                    "blrt" => [],
                    "manifest" => [],
                    "insurance" => []
                ]
            ];
            file_put_contents($this->filePath, json_encode($defaultSchema, JSON_PRETTY_PRINT));
        }

        // Load existing data into memory (Caching the file content)
        $this->documents = json_decode(file_get_contents($this->filePath), true);
    }

    /**
     * Finds the highest ID in a table to ensure unique primary keys.
     */
    public function lastId(string $table): int {
        $ids = array_column($this->documents['documents'][$table], "id");
        return empty($ids) ? 0 : (int) max($ids);
    }

    /**
     * Merges new data and saves the entire state back to the JSON file.
     */
    public function save(string $table, array $data): void {
        $merged = array_merge($data, $this->documents['documents'][$table]);
        
        // Sort by ID to maintain data integrity
        usort($merged, fn($a, $b) => $a['id'] <=> $b['id']); 

        $this->documents['documents'][$table] = $merged;
        file_put_contents($this->filePath, json_encode($this->documents, JSON_PRETTY_PRINT));
    }

    /**
     * Updates the status field for a specific set of IDs.
     */
    public function updateStatus(string $table, array $ids): void {
        foreach ($this->documents['documents'][$table] as &$item) {
            if (in_array($item['id'], $ids)) {
                $item['status'] = true;
            }
        }
        // Save the updated status to the file
        file_put_contents($this->filePath, json_encode($this->documents, JSON_PRETTY_PRINT));
    }
   
    /**
     * Filters and returns records based on their status (e.g., pending or processed).
     */
    public function list(string $table, bool $status): array {
        return array_filter($this->documents['documents'][$table], fn($item) => $item['status'] === $status);
    }

}

/**
 * MySQL Implementation (Skeleton).
 * Ready to be implemented using PDO or any MySQL driver without changing 
 * the business logic in the Services.
 */
class Mysql implements DatabaseManager {
    public function lastId(string $table): int { /* Implementation for SQL MAX(id) */ return 0; }
    public function save(string $table, array $data): void { /* SQL INSERT implementation */ }
    public function updateStatus(string $table, array $ids): void { /* SQL UPDATE implementation */ }
    public function list(string $table, bool $status): array { /* SQL SELECT with WHERE status = ? */ return []; }
}

/**
 * Connection Wrapper (Strategy Pattern).
 * This class decides which database driver to use based on environment settings.
 * It uses the Container to resolve dependencies, ensuring a Singleton-like behavior.
 */
class Connection {
    private DatabaseManager $driver;

    public function __construct(private Container $container) {
        // Dynamic Driver Selection based on ENV configuration
        $dbType = getenv("DB"); 
        
        $class = match ($dbType) {
            'JSON' => Json::class,
            'MYSQL' => Mysql::class,
            default => Json::class // Fallback to JSON
        };

        // Delegate instantiation to the Container for Autowiring support
        $this->driver = $this->container->resolve($class);
    }

    // Proxy methods: Forwarding calls to the selected driver
    public function lastId($table) { return $this->driver->lastId($table); }
    public function save($table, $array) { $this->driver->save($table, $array); }
    public function updateStatus($table, $array) { $this->driver->updateStatus($table, $array); }
    public function list($table, $status) { return $this->driver->list($table, $status); }
}



// ============================================================
// SERVICE CLASSES (BUSINESS LOGIC LAYER)
// ============================================================
/**
 * These Service classes extend the BaseService to handle specific 
 * business logic for each document type. 
 * They manage data generation, calculations, and status updates, 
 * ensuring that the workflow transitions correctly from Freights 
 * to Insurance.
 */

/**
 * Handles the creation of new Freight records with randomized data 
 * to simulate real-world transport entries.
 */
class FreightService extends BaseService{
    protected string $tableName = 'freight';
    // The constructor calls the parent to initialize the Database Connection
    public function __construct(protected Connection $data){
        parent::__construct($data);
    }

    public function create($readline){
        $array = [];
        $towns = ["Stelenbosch","Somerset West", "Mitchells Plain","Cape Town"];
        $customers = ["Jorge","Steven", "Wakalo","Anthony", "Mikaela"];
        // Get the next available ID
        $id = $this->data->lastId($this->tableName) + 1;
        // Generate the number of freights requested in user input
        for ($i=0; $i < $readline[2]; $i++) { 
            $array [] = [
                "id" => $id + $i,
                "date" => date('d-M'),
                "origin" => "paarl",
                "destination" => $towns[rand(0,3)],
                "customer" => $customers[rand(0,4)],
                "weight" =>  rand(10,2000),
                // Initially false (no BLRT generated yet)
                "status" => false,
            ];
        }
        $this->data->save($this->tableName,$array);
    }
    
}

/**
 * Transforms available Freights into BLRT documents and calculates amounts.
 */
class BlrtService extends BaseService{
    protected string $tableName = 'blrt';

    public function __construct(protected Connection $data){
        parent::__construct($data);
    }

    public function generate($readline){
        // Fetch only freights that haven't been processed yet (status false)
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
                // Example pricing logic = R4
                "amount" => round($item["weight"] * 4,2),
                "status" => false
            ];
            $id++;
        }
        // Update processed Freights to 'true' and save new BLRTs
        $this->data->updateStatus('freight',array_column($freights,'id'));
        $this->data->save($this->tableName,$blrts);
    }

} 

/**
 * Aggregates all pending BLRTs into a single Manifest and calculates totals.
 */
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
            "blrts" => [],
            "weight_total" => 0,
            "amount_total" => 0,
            "status" => false
        ];
        // Summing up totals from all processed BLRTs
        foreach ($blrts as $item) {
            $manifest["weight_total"] += $item["weight"];
            $manifest["amount_total"] += $item["amount"];
            array_push($manifest["blrts"],$item["id"]);
        }
        $this->data->updateStatus('blrt',array_column($blrts,'id'));
        $this->data->save($this->tableName,[$manifest]);

    }
}

/**
 * Aggregates Manifests to generate Insurance documents.
 */
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



// ============================================================
// CONCRETE CONTRACT CLASSES (CONTROLLERS)
// ============================================================
/**
 * Contract classes implementing the DocumentManager interface.
 * This allows the 'create' and 'list' methods to execute specific 
 * business rules based on the user's input, delegating the 
 * actual work to the corresponding Service.
 */
      
class FreightContract implements DocumentManager{

    // Dependency Injection of the specific Service via Constructor
    public function __construct(private FreightService $service){}

    public function create(array $readline):void{
        $this->service->create($readline);
    }
    
    // Converting string input ('true'/'false') to boolean for the Service
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


// ============================================================
// CONTROLLER CLASSES
// ============================================================
/**
 * This class orchestrates the document management flow. It maps user input 
 * to specific document types and delegates actions (Create/List) using 
 * dynamically resolved dependencies from the DI Container.
 */

class LadingController {

    // Injecting the Container to enable Autowiring for document managers
    public function __construct(private Container $container) {} 

    /**
     * Map numerical input codes to their respective Class names.
     * Used for dynamic dependency resolution.
     */
    public function matchClass(string $code): string {
        return match ($code) {
            '1' => FreightContract::class,    
            '2' => BlrtContract::class,
            '3' => ManifestContract::class,
            '4' => InsuranceContract::class,
            default => throw new Exception("Invalid document code provided."),
        };
    }    

    /**
     * Main entry point for document actions.
     * Resolves the required class and triggers the specific action (Create or List).
     */
    public function createGenerateListDocument(array $readline): void {
        // Resolve the specific manager instance using the Container (Reflection-based DI)
        $resolvedClass = $this->container->resolve($this->matchClass($readline[1]));

        // Direct the flow based on the 'Action' code (index 0)
        match ($readline[0]) {
            '1' => $this->createGenerateDocument($resolvedClass, $readline),
            '2' => $this->listDocument($resolvedClass, $readline),
        };
    }

    /**
     * Executes the creation/generation logic for the given document.
     */
    public function createGenerateDocument(DocumentManager $docManager, array $readline): void {
        $docManager->create($readline);
    }

    /**
     * Executes the listing/filtering logic for the given document.
     */
    public function listDocument(DocumentManager $docManager, array $readline): void {
        $docManager->list($readline);
    }
}



// ============================================================
// MAIN FUNCTION FOR DATA ENTRY
// ============================================================
/**
 * Handles user interaction and data validation.
 * Returns an array with validated data or null to exit.
 */
function inputValidate() {
    $action = 0;
    $document = 0;
    $quantity = 0;
    $status = 'false';

    // --- STEP 1: ACTION SELECTION ---
    $input = 0;
    while (!in_array($input, ['1', '2', 'x'])) {
        system('clear');
        echo " ________________________________\n";
        echo "| CODE        ACTION             |\n";
        echo "|  1          CREATE/GENERATE    |\n";
        echo "|  2          LIST               |\n";
        echo "|                                |\n";
        echo "|  x          EXIT               |\n";
        echo "|________________________________|\n";
        $input = readline("ENTER: ");
        if (strtolower($input) === 'x') return null;
    }
    $action = $input;

    // --- STEP 2: DOCUMENT SELECTION ---
    $input = 0;
    while (!in_array($input, ['1', '2', '3', '4', 'x', 'c'])) {
        system('clear');
        echo " ________________________________\n";
        echo "| CODE        DOCUMENT           |\n";
        echo "|  1          FREIGHT            |\n";
        echo "|  2          BLRT               |\n";
        echo "|  3          MANIFEST           |\n";
        echo "|  4          INSURANCE          |\n";
        echo "|                                |\n";
        echo "|  c          CANCEL (BACK)      |\n";
        echo "|  x          EXIT               |\n";
        echo "|________________________________|\n";
        $input = readline("ENTER: ");
        
        if (strtolower($input) === 'x') return null;
        if (strtolower($input) === 'c') return 'restart'; // Return a flag to restart
    }
    $document = $input;

    // --- STEP 3: QUANTITY (FOR CREATE ACTION) ---
    if ($action == 1 && $document == 1) {
        $input = 0;
        while ($input !== 'x' && $input !== 'c' && (int)$input <= 0) {
            system('clear');
            echo " ________________________________\n";
            echo "| NUMBER > 0  FREIGHT'S QUANTITY |\n";
            echo "|                                |\n";
            echo "|  c          CANCEL (BACK)      |\n";
            echo "|  x          EXIT               |\n";
            echo "|________________________________|\n";
            $input = readline("ENTER: ");
            
            if (strtolower($input) === 'x') return null;
            if (strtolower($input) === 'c') return 'restart';
        }
        $quantity = (int)$input;
    }

    // --- STEP 4: STATUS (FOR LIST ACTION) ---
    if ($action == 2) {
        $input = '';
        while ($input !== 'x' && $input !== 'c' && !in_array(strtolower($input), ['true', 'false'])) {
            system('clear');
            echo " ________________________________\n";
            echo "| TRUE/FALSE  DOCUMENT'S STATUS  |\n";
            echo "|                                |\n";
            echo "|  c          CANCEL (BACK)      |\n";
            echo "|  x          EXIT               |\n";
            echo "|________________________________|\n";
            $input = readline("ENTER: ");
            
            if (strtolower($input) === 'x') return null;
            if (strtolower($input) === 'c') return 'restart';
        }
        $status = strtolower($input);
    }

    return [$action, $document, $quantity, $status];
}

// Initialize the Controller once (using Singleton pattern via Container)
$ladingController = new LadingController(new Container());

/**
 * Main Application Loop.
 * Keeps the script running until the user explicitly exits.
 */
while (true) {
    // 1. Capture and validate user input
    $readline = inputValidate();

    // 2. Handle 'Exit' action
    if ($readline === null) {
        exit("\nExecution finished by user.\n");
    }

    // 3. Handle 'Cancel/Restart' action
    if ($readline === 'restart') {
        continue; 
    }

    // 4. Execute the logic and stay in the loop for the next command
    $ladingController->createGenerateListDocument($readline);

    // Optional: Pause to let the user see the result before clearing the screen again
    readline("\nPress ENTER to return to menu...");
}




