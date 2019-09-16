# Outil spip-cli

spip-cli est un outil pour commander SPIP depuis la ligne de commandes.

## Documentation

https://contrib.spip.net/SPIP-Cli

## Utiliser spip-cli

Pour connaître les commandes disponibles, lancer `spip` dans un shell

```
$ spip

Ligne de commande pour SPIP version 0.2.3

Usage:
  [options] command [arguments]

Options:
  --help           -h Display this help message.
  --quiet          -q Do not output any message.
  --verbose        -v|vv|vvv Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
  --version        -V Display this application version.
  --ansi              Force ANSI output.
  --no-ansi           Disable ANSI output.
  --no-interaction -n Do not ask any interactive question.

Available commands:
  dl                 Télécharger SPIP dans un dossier (par défaut, la dernière version stable)
  help               Displays help for a command
  install            Installer la base de données et le premier utilisateur.
  installer          Installer la base de données et le premier utilisateur.
  list               Lists commands
cache
  cache:desactiver   Désactive le cache de spip pendant 24h.
  cache:reactiver    Réactive le cache de spip.
  cache:vider        Vider le cache.
core
  core:installer     Installer la base de données et le premier utilisateur.
  core:preparer      Préparer les fichiers pour installer SPIP correctement.
  core:telecharger   Télécharger SPIP dans un dossier (par défaut, la dernière version stable)
(etc.)
```

Dans la version 0.2.3, `spip-cli` permet :

* de télécharger SPIP dans un dossier,
* d'installer la base de données de SPIP et le premier utilisateur,
* vider et activer/désactiver le cache.
* utiliser les fonctions propre() et typo()

## synchro SPIP
> Synchroniser un spip distant sur un spip local, bdd / fichiers / modif des metas
>
> ATTENTION, pour l'instant ne fonctionne que sur une bdd en mysql

3 actions possibles :
* `spip synchro:init` creation d'un fichier json : synchroSPIP.json à la racine du SPIP, il restera un peu de configuration à faire.
* `spip synchro:bdd` pour lancer la synchro de la bdd et la modif des metas
* `spip synchro:fichiers` pour lancer la synchro des fichiers via rsync

`spip synchro:bdd` : Il y a 3 args facultatifs
* -v : verbeux
* -b ou --backup: forcer le backup local de la bdd
* -r ou --rsync: lancer à la fin les commandes rsync du script synchro:fichiers

`spip synchro:fichiers`: Il y a 1 arg facultatif
* -v : verbeux

Fichier de configuration synchroSPIP.json
* Il y a 2 façons pour ouvrir une connexion ssh :
	* via : user / hostName / port ex : `ssh toto@spip.net -p 1234`
	* via: host (il faut l'avoir défini dans .ssh/config) ex: `ssh mon_host_spip`
	* Il faut avoir une cle ssh
* Configuration pour le rsync : Chaque ligne représente : chemin local => chemin distant:
	* le chemin local peut-être en relatif
	* le chemin distant doit etre en absolue et se terminer par '/'

exemple :
```json
"rsync": {
	"IMG": "chemin abs vers IMG/",
	"plugins": "chemin abs vers plugins/"
}
```

