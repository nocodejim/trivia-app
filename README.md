Real-Time Trivia Web ApplicationThis project is a real-time trivia game built with a PHP/MySQL backend, designed for deployment on shared hosting like Hostinger but developed locally using a portable Docker environment.It allows hosts to create game sessions and players to join using a unique code for a real-time, interactive trivia experience.🚀 Local Development Setup (Docker)This project uses Docker to create a consistent and portable development environment. This is the recommended way to run the application locally.PrerequisitesDocker Desktop installed and running on your system.Step 1: Populate Project FilesEnsure all the files provided in the implementation phases are placed in their correct directories within this project folder (api/, config/, etc.).Step 2: Configure the Database ConnectionOpen the config/database.php file and ensure the credentials match the services defined in docker-compose.yml:// config/database.php
define('DB_HOST', 'db');         // The hostname 'db' is the name of our database service
define('DB_NAME', 'trivia_db');  // From MYSQL_DATABASE in docker-compose.yml
define('DB_USER', 'user');       // From MYSQL_USER in docker-compose.yml
define('DB_PASS', 'password');   // From MYSQL_PASSWORD in docker-compose.yml
Step 3: Build and Run the ApplicationFrom the root directory of the project (/trivia-app/), run the following command in your terminal:docker-compose up -d
This command will download the required PHP and MySQL images, build your application container, and start both the web server and the database in the background.The first time you run this, it may take a few minutes.Step 4: Set Up the DatabaseWith the containers running, execute this command from your terminal to import the database schema and sample questions:docker-compose exec -T db mysql -u root -prootpassword trivia_db < ./sql/setup.sql
This command executes the setup.sql script inside your running database container.Step 5: Accessing the ApplicationYour application's API is now running and accessible on your local machine:API URL: http://localhost:8080/Database Port (for external clients): 3306Useful Docker CommandsStop the application: docker-compose downView container logs: docker-compose logs -f app (for the PHP server) or docker-compose logs -f db (for the database).🧪 Testing the APIYou can use a tool like Postman to interact with your running API endpoints.Create a New Game SessionMethod: POSTURL: http://localhost:8080/api/create_session.phpBody (raw, JSON):{
    "session_name": "Docker Test Game",
    "host_email": "host@test.com",
    "total_questions": 10,
    "time_per_question": 20
}
Join an Existing SessionMethod: POSTURL: http://localhost:8080/api/join_session.phpBody (raw, JSON): (Use the session_code returned from the create request){
    "session_code": "YOUR_CODE_HERE",
    "player_name": "DockerPlayer"
}
