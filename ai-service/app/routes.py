import io

from flask import Blueprint, current_app, jsonify, request, send_file
from werkzeug.utils import secure_filename

from . import services


api = Blueprint("api", __name__)


def _json_body(required):
    data = request.get_json(silent=True)
    if not isinstance(data, dict):
        return None, (jsonify(message="Corps JSON requis."), 400)
    missing = [field for field in required if not data.get(field)]
    if missing:
        return None, (jsonify(message="Champs requis manquants : "+", ".join(missing)), 422)
    return data, None


def _run(operation, required):
    data, error = _json_body(required)
    if error:
        return error
    try:
        return jsonify(operation(data))
    except services.ServiceError as error:
        return jsonify(message=str(error)), error.status


@api.post("/dialogue")
def dialogue():
    return _run(services.chat, ["question"])


@api.post("/plan")
def plan():
    return _run(services.plan, ["context"])


@api.post("/generation")
def generation():
    return _run(services.generate, ["section_title"])


@api.post("/humanisation")
def humanisation():
    return _run(services.humanize, ["text"])


@api.post("/webographie")
def webographie():
    return _run(services.research, ["question"])


@api.post("/extract")
def extract():
    file = request.files.get("file")
    if not file or not file.filename:
        return jsonify(message="Fichier requis."), 422
    filename = secure_filename(file.filename)
    content = file.read(current_app.config["MAX_CONTENT_LENGTH"] + 1)
    if len(content) > current_app.config["MAX_CONTENT_LENGTH"]:
        return jsonify(message="Fichier trop volumineux."), 413
    try:
        return jsonify(services.extract(filename, content))
    except services.ServiceError as error:
        return jsonify(message=str(error)), error.status


@api.post("/export")
def export():
    data, error = _json_body(["format", "title"])
    if error:
        return error
    if data["format"] not in {"pdf", "docx"}:
        return jsonify(message="Format d’export non pris en charge."), 422
    if not isinstance(data.get("sections", []), list) or len(data.get("sections", [])) > 200:
        return jsonify(message="Structure de rapport invalide."), 422
    try:
        content, mimetype = services.export_report(data)
    except services.ServiceError as error:
        return jsonify(message=str(error)), error.status
    extension = data["format"]
    return send_file(
        io.BytesIO(content),
        mimetype=mimetype,
        as_attachment=True,
        download_name=f"rapport.{extension}",
    )
