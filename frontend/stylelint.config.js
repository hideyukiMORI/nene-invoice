// nene2 フリート統一 stylelint gate（規約 05 §3.1 コピペ正本・#65 arm）。
// per-repo 台帳 registries.jsonc（同ディレクトリ）を読み、既存の構造負債を
// (rule,file) 単位で grandfather しつつ、新規違反のみ赤にする（P2 B1 / standards 2.0.x）。
import { stylelintConfigFor } from '@hideyukimori/nene2-standards/stylelint'

export default stylelintConfigFor('nene-invoice')
