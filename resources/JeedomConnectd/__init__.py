import argparse
import logging

parser = argparse.ArgumentParser(description="Daemon for Jeedom plugin")
parser.add_argument("--trace", help="Log Level for the daemon", type=int)
args, unknown = parser.parse_known_args()

_log_level = args.trace
# Configurer le système global de journalisation
logging.basicConfig(
    level=logging.DEBUG if _log_level == 1 else logging.WARNING,
    format='[%(asctime)-15s][%(levelname)s] : %(message)s',
    datefmt="%Y-%m-%d %H:%M:%S"
)

# Créer un logger pour ce package/module
logger = logging.getLogger('JCd')

# Rendre le logger disponible pour l'ensemble du module
__all__ = ["logger"]
