import argparse
import json
import sys


def main() -> int:
    sys.stdin.reconfigure(encoding="utf-8")
    sys.stdout.reconfigure(encoding="utf-8")

    parser = argparse.ArgumentParser(description="Generate local multilingual text embeddings.")
    parser.add_argument(
        "--model",
        default="sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2",
    )
    args = parser.parse_args()

    try:
        from fastembed import TextEmbedding

        payload = json.load(sys.stdin)
        texts = payload.get("texts", [])

        if not isinstance(texts, list) or any(not isinstance(text, str) for text in texts):
            raise ValueError("texts must be a list of strings")

        model = TextEmbedding(model_name=args.model)
        vectors = [vector.tolist() for vector in model.embed(texts)]

        print(
            json.dumps(
                {
                    "model": args.model,
                    "dimensions": len(vectors[0]) if vectors else 0,
                    "vectors": vectors,
                },
                ensure_ascii=False,
            )
        )

        return 0
    except Exception as exc:  # noqa: BLE001 - the CLI must surface the real error
        print(f"embedding failed: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
