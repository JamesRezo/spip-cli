# Installation de spip-cli

## Récuperer le code

On récupère l'outil spip-cli dans un répertoire partagé

```
sudo svn checkout svn://zone.spip.org/spip-zone/_outils_/spip-cli/trunk /opt/spip-cli
```

## Utiliser l'exécutable en ligne de commande

Sur Linux, pour pouvoir utiliser la commande `spip` dans un shell

```
sudo ln -s /opt/spip-cli/spip.php /usr/bin/spip
```
