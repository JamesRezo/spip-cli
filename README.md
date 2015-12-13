# Outil spip-cli

spip-cli est un outil pour commander SPIP depuis la ligne de commandes.

## Installer spip-cli

Voir le fichier [./INSTALL.md](INSTALL.md).

## Utiliser spip-cli

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
