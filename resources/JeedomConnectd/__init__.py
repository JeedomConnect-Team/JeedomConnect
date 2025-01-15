import logging

# Configurer le système global de journalisation
logging.basicConfig(
    level=logging.WARNING,
    format='[%(asctime)-15s][%(levelname)s] : %(message)s',
    datefmt="%Y-%m-%d %H:%M:%S"
)

# Créer un logger pour ce package/module
logger = logging.getLogger('JCd')

# Rendre le logger disponible pour l'ensemble du module
__all__ = ["logger"]
