#!/usr/bin/env python3
"""Build the screenshot tour with FFmpeg. Run from any directory; no Python packages required."""
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
SCENES = [
    ('dashboard.png', '01 / STORE OVERVIEW', 'Daily trading, outstanding balances and stock alerts'),
    ('pos.png', '02 / POINT OF SALE', 'Product search, weighted items and split payments'),
    ('products.png', '03 / PRODUCT CATALOG', 'Pricing, identifiers and stock settings in one workspace'),
    ('reports.png', '04 / BUSINESS REPORTS', 'Review performance by period and export operational records'),
]


def main():
    if not shutil.which('ffmpeg'):
        raise SystemExit('Install FFmpeg, then run this script again.')
    output = ROOT / 'docs/media'
    output.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='freshmart-tour-') as temporary:
        temp = Path(temporary)
        clips = []
        for index, (filename, title, subtitle) in enumerate(SCENES):
            titlefile, subtitlefile = temp / f'{index}-title.txt', temp / f'{index}-subtitle.txt'
            titlefile.write_text(title)
            subtitlefile.write_text(subtitle)
            clip = temp / f'{index}.mp4'
            # Show a readable top viewport; originals remain unchanged in screenshots/.
            filters = (
                "scale=1440:-2,crop=1440:900:0:0,"
                "pad=1440:1080:0:150:color=0x10151c,"
                f"drawtext=textfile={titlefile}:fontsize=30:fontcolor=white:x=40:y=28,"
                f"drawtext=textfile={subtitlefile}:fontsize=21:fontcolor=0xb8c5d4:x=40:y=76,"
                "drawtext=text='FreshMart ERP  |  Demo screenshots':fontsize=16:fontcolor=0x8495a8:x=40:y=1058,"
                "fade=t=in:st=0:d=0.3,fade=t=out:st=5.7:d=0.3,format=yuv420p"
            )
            subprocess.run([
                'ffmpeg', '-hide_banner', '-loglevel', 'error', '-y', '-loop', '1',
                '-i', str(ROOT / 'docs/screenshots' / filename), '-t', '6', '-vf', filters,
                '-r', '24', '-c:v', 'libx264', '-preset', 'fast', '-crf', '24',
                '-threads', '2', '-an', str(clip),
            ], check=True)
            clips.append(clip)
        manifest = temp / 'clips.txt'
        manifest.write_text(''.join(f"file '{clip}'\n" for clip in clips))
        subprocess.run([
            'ffmpeg', '-hide_banner', '-loglevel', 'error', '-y', '-f', 'concat', '-safe', '0',
            '-i', str(manifest), '-c', 'copy', '-movflags', '+faststart',
            '-metadata', 'title=FreshMart ERP - Screenshot product tour',
            str(output / 'freshmart-tour.mp4'),
        ], check=True)
    print(output / 'freshmart-tour.mp4')


if __name__ == '__main__':
    main()
