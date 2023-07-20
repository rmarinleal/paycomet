# PayComet technical test

Here you can find a full docker environment to perform the requested opperations

## Requirements

Docker installed
Docker-compose installed
Git installed

## Installation

Clone the repository on your local machine
```bash
git clone https://github.com/rmarinleal/paycomet.git
```
Start the containers with the docker-compose file
```bash
docker-compose up -d --build
```
Once all environments are up create the vendors using composer
```bash
docker exec php composer install
```
Access the project with your browser by typing the url
```bash
localhost:8080
```
