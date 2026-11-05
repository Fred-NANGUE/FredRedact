import os
import hmac

from flask import Flask, jsonify, request
from werkzeug.exceptions import HTTPException

from .routes import api


def create_app(test_config=None):
    app = Flask(__name__)
    app.config.from_mapping(
        AI_SERVICE_KEY=os.getenv("AI_SERVICE_KEY", ""),
        GEMINI_API_KEY=os.getenv("GEMINI_API_KEY", ""),
        GEMINI_MODEL=os.getenv("GEMINI_MODEL", "gemini-3.5-flash-lite"),
        MAX_CONTENT_LENGTH=16 * 1024 * 1024,
        AI_TIMEOUT=int(os.getenv("AI_TIMEOUT", "90")),
    )
    if test_config:
        app.config.update(test_config)

    @app.get("/health")
    def health():
        return {"status": "ok"}

    @app.before_request
    def require_internal_key():
        if request.path.startswith("/api/"):
            expected = app.config["AI_SERVICE_KEY"]
            supplied = request.headers.get("X-AI-Service-Key", "")
            if not expected or not supplied or not hmac.compare_digest(expected, supplied):
                return jsonify(message="Accès refusé."), 401

    @app.errorhandler(HTTPException)
    def handle_http_error(error):
        return jsonify(message=error.description), error.code

    app.register_blueprint(api, url_prefix="/api")
    return app
