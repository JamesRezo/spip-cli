# Installation de spip-cli

## Récuperer le code

On récupère l'outil spip-cli dans un répertoire partagé

```
sudo svn checkout svn://zone.spip.org/spip-zone/_outils_/spip-cli/trunk /opt/spip-cli
```

## Utiliser l'exécutable en ligne de commande

Sur Linux, pour pouvoir utiliser la commande `spip` dans un shell :

```
sudo ln -s /opt/spip-cli/spip.php /usr/bin/spip
```

## Activer l'autocomplétion

Sur Linux il est possible d'avoir l'autocomplétion de toutes les options. Pour cela il faut copier le fichier `spip_completion.sh` dans le dossier dédié du système :

```
sudo ln -s /opt/spip-cli/spip_completion.sh /etc/bash_completion.d/spip
```
