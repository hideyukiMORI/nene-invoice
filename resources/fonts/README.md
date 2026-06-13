# Bundled PDF fonts

These TrueType fonts back the **heading font** selector (ゴシック / 明朝) on
quote / invoice PDFs (Issue #449). mPDF ships no Japanese font, so they are
bundled and registered in `NeneInvoice\Pdf\MpdfFactory`.

| File          | Family       | Heading font value |
| ------------- | ------------ | ------------------ |
| `ipaexg.ttf`  | IPAexGothic  | `gothic` (default) |
| `ipaexm.ttf`  | IPAexMincho  | `mincho`           |

## License

IPAex fonts are distributed under the **IPA Font License Agreement v1.0**
(`IPA_Font_License_Agreement_v1.0.txt` in this directory). The license permits
redistribution provided the license text travels with the fonts — hence its
inclusion here and in release ZIPs.

- Source: IPA / Moji Joho Kiban (https://moji.or.jp/ipafont/)
- Package: IPAexfont00401

Do not rename the `.ttf` files: the names are referenced by `MpdfFactory`.
