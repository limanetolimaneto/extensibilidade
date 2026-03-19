# Extensibility: PHP DI Container & Document Workflow

A PHP-based command-line application that demonstrates **Dependency Injection (DI)**, **Reflection API**, and the **Strategy Pattern**. This project simulates a logistics workflow, processing data from Freights to Insurance documents while maintaining a decoupled and extensible architecture.

## 🚀 Key Features

*   **Custom DI Container**: Implements **Autowiring** using the PHP Reflection API to resolve class dependencies automatically.
*   **Performance Optimization**: Uses an **Instance Registry (Singleton Pattern)** within the Container to mitigate the overhead of Reflection.
*   **Strategy Pattern (Persistence)**: Seamlessly switch between **JSON** file storage and **MySQL** (stub) without changing business logic.
*   **Clean Architecture**: Follows **SOLID** principles, specifically Dependency Inversion (D) and Interface Segregation (I).
*   **Interactive CLI**: A user-friendly command-line interface with recursive menus and data validation.

## 🏗️ Architecture Overview

The project is divided into specialized layers:

1.  **UI Layer (`inputValidate`)**: Handles user interaction and CLI data formatting.
2.  **Controller Layer (`LadingController`)**: Orchestrates the flow and delegates actions to specific contracts.
3.  **Service Layer (`BaseService` & Subclasses)**: Contains the core business logic and data aggregation rules.
4.  **Persistence Layer (`DatabaseManager` & `Connection`)**: Manages how data is stored, abstracting the storage engine from the rest of the app.

## 🛠️ Design Patterns Used

*   **Dependency Injection (DI)**: Injected via constructors to ensure low coupling.
*   **Singleton**: Managed by the Container to reuse instances and save memory.
*   **Template Method**: Used in `BaseService` to share common logic (like listing) among children.
*   **Factory/Strategy**: The `Connection` class dynamically selects the database driver based on environment variables.

## 💻 Installation & Usage

1.  **Clone the repository**:
    ```bash
    git clone https://github.com/limanetolimaneto/extensibility
    cd extensibility
    ```

2.  **Configure Environment**:
    The app uses `getenv()` for configuration. Ensure your environment has:
    *   `DB=JSON`
    *   `DB_NAME=docs.json`

3.  **Run the script**:
    ```bash
    php index.php
    ```

## 📝 Author
**Lima Neto** - [GitHub](https://github.com/limanetolimaneto)
