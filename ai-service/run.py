import os

from dotenv import load_dotenv

load_dotenv(os.path.join(os.path.dirname(__file__), ".env"))

from app import create_app


app = create_app()


if __name__ == "__main__":
    app.run(
        host=os.getenv("AI_SERVICE_HOST", "127.0.0.1"),
        port=int(os.getenv("AI_SERVICE_PORT", "5001")),
        debug=False,
    )
