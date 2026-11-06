import io
import pathlib
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1]))

from app import create_app
from app import services


class ApiTests(unittest.TestCase):
    def setUp(self):
        self.app = create_app({"TESTING": True, "AI_SERVICE_KEY": "test-key"})
        self.client = self.app.test_client()
        self.headers = {"X-AI-Service-Key": "test-key"}

    def test_api_requires_internal_key(self):
        response = self.client.post("/api/dialogue", json={"question": "Bonjour"})
        self.assertEqual(response.status_code, 401)

    def test_health_is_public(self):
        response = self.client.get("/health")
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json["status"], "ok")

    @patch("app.services._gemini", return_value=("Réponse factuelle", 18))
    def test_dialogue_returns_gemini_text(self, _gemini):
        response = self.client.post(
            "/api/dialogue",
            headers=self.headers,
            json={
                "question": "Et ensuite ?",
                "context": {"theme": "Suivi de bases de données"},
                "history": [{"question": "Comment commencer ?", "reponse": "Présentez le contexte."}],
                "attachment": {"name": "consignes.pdf", "text": "Décrire les objectifs du stage."},
            },
        )
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json["text"], "Réponse factuelle")
        self.assertEqual(_gemini.call_args.kwargs["history"][0]["question"], "Comment commencer ?")
        self.assertIn("Markdown lisible", _gemini.call_args.args[0])
        self.assertIn("consignes.pdf", _gemini.call_args.args[0])
        self.assertIn("Décrire les objectifs du stage.", _gemini.call_args.args[0])

    def test_json_endpoints_reject_missing_fields(self):
        response = self.client.post("/api/generation", headers=self.headers, json={})
        self.assertEqual(response.status_code, 422)

    @patch("app.services.verify_public_url", return_value=True)
    @patch("app.services._json_response", return_value=(
        {"sources": [{"titre": "Documentation", "url": "https://example.com", "resume": "Résumé"}]},
        18,
    ))
    def test_research_checks_suggested_links(self, _response, _verify):
        response = self.client.post(
            "/api/webographie",
            headers=self.headers,
            json={"question": "Documentation", "context": {}},
        )
        self.assertEqual(response.status_code, 200)
        self.assertTrue(response.json["sources"][0]["lien_verifie"])

    def test_docx_export_can_be_extracted_again(self):
        content, _mimetype = services.export_report({
            "format": "docx",
            "title": "Rapport de test",
            "sections": [{"titre": "Introduction", "contenu": "Texte conservé."}],
        })
        result = services.extract("rapport.docx", content)
        self.assertIn("Texte conservé.", result["text"])

    def test_pdf_export_produces_a_readable_pdf(self):
        content, mimetype = services.export_report({
            "format": "pdf",
            "title": "Rapport de test",
            "sections": [{"titre": "Introduction", "contenu": "Texte conservé."}],
        })
        self.assertEqual(mimetype, "application/pdf")
        result = services.extract("rapport.pdf", content)
        self.assertIn("Texte conservé.", result["text"])

    def test_link_verification_rejects_local_hosts(self):
        self.assertFalse(services.verify_public_url("https://127.0.0.1/admin"))
        self.assertFalse(services.verify_public_url("http://example.com"))


if __name__ == "__main__":
    unittest.main()
