import argparse
import json
import sys


def main() -> int:
    sys.stdout.reconfigure(encoding="utf-8")

    parser = argparse.ArgumentParser(description="Transcribe an audio file with faster-whisper.")
    parser.add_argument("--path", required=True)
    parser.add_argument("--model", default="base")
    parser.add_argument("--device", default="cpu")
    parser.add_argument("--compute-type", default="int8")
    parser.add_argument("--language", default=None)
    args = parser.parse_args()

    try:
        from faster_whisper import WhisperModel

        model = WhisperModel(args.model, device=args.device, compute_type=args.compute_type)
        segments, info = model.transcribe(
            args.path,
            language=args.language or None,
            vad_filter=True,
            beam_size=5,
        )

        parts = []
        rendered_segments = []

        for segment in segments:
            text = segment.text.strip()
            if text:
                parts.append(text)
                rendered_segments.append(
                    {
                        "start": round(float(segment.start), 2),
                        "end": round(float(segment.end), 2),
                        "text": text,
                    }
                )

        print(
            json.dumps(
                {
                    "text": " ".join(parts).strip(),
                    "language": info.language,
                    "language_probability": round(float(info.language_probability), 4),
                    "segments": rendered_segments,
                },
                ensure_ascii=False,
            )
        )

        return 0
    except Exception as exc:  # noqa: BLE001 - the CLI must surface the real error
        print(f"transcription failed: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
