# Gesip


## Pré-requis

Le projet peut tourner sur toute plateforme (Linux, Windows,...) du moment que git, composer, docker et docker-compose sont installées sur votre système.
Ci-dessous, nous détaillerons les instructions nécessaires pour installer ces outils.

A noter: dans un environement "cloisoné", il sera probablement indispensable de configurer votre système pour configurer les proxies adéquats.


### Installation des paquets CentOS nécessaires

Après avoir récupéré les dernières mises à jour des dépots, nous aurons besoin d'installer quelques outils additionnels sur la machine avant de commencer l'installation de Gesip.

```sh
$ sudo yum update
$ sudo yum install -y wget curl git php-cli php-zip unzip py-pip python-dev libffi-dev openssl-dev gcc libc-dev make
```

### Installation de composer

Ensuite il vous faudra **composer** pour installer et maintenir les dépendances utilisées par Symfony.

$ wget https://getcomposer.org/composer-stable.phar
$ sudo mv composer-stable.phar /usr/local/bin/composer


### Installation de Docker

Vous devez installer **docker** sur votre machine.
Les commandes suivantes sont inspirées du [guide d'installation de Docker pour la distribution Linux CentOS](https://docs.docker.com/engine/install/centos).

```sh
$ sudo yum install -y yum-utils
$ sudo yum-config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
$ sudo yum install docker-ce docker-ce-cli containerd.io
$ sudo systemctl start docker
```

### Installation de docker-compose

Afin de monter une infrastructure docker, vous devez installer **docker-compose**.
Les commandes suivantes sont inspirées du [guide d'installation de docker-compose](https://docs.docker.com/compose/install).

```sh
$ sudo curl -L "https://github.com/docker/compose/releases/download/1.26.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
$ sudo chmod +x /usr/local/bin/docker-compose
```

## Lancement

### Installation du projet

```sh
$ git clone https://forge.dgfip.finances.rie.gouv.fr/dgfip/pilotage-exploitation/gesip.git
cd gesip
```

### Configuration

Avant de démarrer vous devez créer un fichier environnement qui contiendra les paramètres de configuration vous permettant d'adapter le projet Gesip à votre système.

Pour créer votre propre fichier d'environnement, vous pouvez dupliquer le fichier .env.sample. Personnalisez ensuite les différents éléments du fichier .env en fonction de votre plateforme d'hébergement.

```sh
$ cp .env.sample .env
```

### Exécution

Pour démarrer le projet, executez simplement la commande qui suit. Cette commande va automatiquement créer les images des conteneurs puis les lancer si ce n'est pas déjà fait. Chaque conteneur représente un composants constituant Gesip (serveur web, serveur PHP, serveur Postgresql).

```sh
$ sudo docker-compose up -d
```

### (Si c'est le premier démarrage du container…)

Dans le cas d'un premier démarrage du container php, il est nécessaire d'indiquer les bons droits d'accès aux dossiers `/documentation` et `/carte-identite`.
Pour ce faire, il est nécessaire d'exécuter les commandes suivantes **dans le container php** fraichement créé :

```sh
$ chown -R www-data:www-data /carte-identite/ /documentation/
$ chmod -R 770 /carte-identite/ /documentation/
```

Dès lors, il sera alors possible d'uploader des fichiers dans ces dossiers.

### Arret

Pour arreter l'execution de Gesip sur la machine, positionnez-vous à la racine du répertoire racine de Gesip et tapez la commande suivante:

```sh
$ sudo docker-compose down
```
## Utilisation de la commande `gesip`

### Installation

Pour pouvoir utiliser la commande `gesip` et pouvoir effectuer des traitements de manière plus facile sur les conteneurs docker du projet, il faut tout d'abord autoriser le script à l'exécution.

```sh
$ chmod +x gesip
```

Ensuite, et pour pouvoir y avoir accès de n'importe où dans un terminal, il faut rajouter un alias vers le fichier `gesip` situé à la racine du projet.
Pour cela, il va falloir ouvrir ou créer le fichier `.bashrc` de l'utilisateur courant puis d'y ajouter dedans la ligne suivante :

```sh
alias gesip="chemin vers le fichier gesip"
```

Pour pouvoir utiliser le nouvel alias, il suffit soit de relancer un terminal, soit d'exécuter la commande suivante :

```sh
$ source ~/.bashrc
```

Enfin, nous pouvons utiliser la commande `gesip` directement dans le terminal afin de pouvoir exécuter de manière plus facile des traitements sur les containers docker.

```sh
$ gesip
-- Commandes disponibles --
gesip cd                    Place le terminal dans le dossier du projet
gesip up                    Démarre les services
gesip down                  Stop les services
gesip pull [service]        Récupère les nouvelles images ou pour un [service] en particulier
gesip php [commande]        Lance `php [commande]`
gesip composer [commande]   Lance `composer [commande]`
gesip symfony [commande]    Lance `php bin/console [commande]`
gesip phpunit [commande]    Lance `phpunit [commande]`
gesip phpcs                 Lance `phpcs`
gesip tests                 Lance `phpunit` puis `phpcs`
```

### Mettre à jour les images

Les services utilisent des images qui peuvent être mises à jour avec la commande :

```sh
$ gesip pull
```

De cette façon lorsque nous démarrerons à nouveau les services, Docker utilisera automatiquement ces nouvelles images.


## Générer la partie `css` de l'application Gesip

Pour pouvoir utiliser la génération des feuilles de style, nous utilisons `gulp`.
Nous devons d'abord l'installer sur notre machine locale.

```sh
$ npm install -g gulp-cli
```

Puis installer les dépendances de notre projet local.

```sh
$ cd app/
$ npm install
```

Enfin, afin de compiler les fichiers scss situés dans le dossier `app/assets/scss`, nous devons exécuter la commande suivante :

```sh
$ gulp
```
