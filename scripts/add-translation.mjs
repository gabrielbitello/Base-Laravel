// Insere/atualiza traduções nos arquivos lang/*.json preservando o arquivo original:
// a inserção é textual (uma linha nova), em ordem alfabética, com a indentação
// detectada do próprio arquivo. Uso:
//
//   make translate KEY="English key" PT="texto" [ES="texto"] [UPDATE=1]
//
// - KEY é a string em inglês usada no código via __('...')
// - PT grava em lang/pt_BR.json e ES em lang/es.json (ao menos um é obrigatório)
// - UPDATE=1 substitui o valor caso a chave já exista com valor diferente
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const langDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../lang');
const targets = [
	{ file: path.join(langDir, 'pt_BR.json'), value: process.env.PT?.trim(), name: 'pt_BR' },
	{ file: path.join(langDir, 'es.json'), value: process.env.ES?.trim(), name: 'es' },
];

const key = process.env.KEY?.trim() ?? '';
const update = process.env.UPDATE === '1';

if (key === '') {
	console.error('Uso: make translate KEY="English key" PT="texto" [ES="texto"] [UPDATE=1]');
	process.exit(1);
}

const selected = targets.filter((target) => target.value !== undefined && target.value !== '');

if (selected.length === 0) {
	console.error('Uso: make translate KEY="English key" PT="texto" [ES="texto"] [UPDATE=1]');
	process.exit(1);
}

const keyLineRegex = /^(\s*)"((?:[^"\\]|\\.)*)":/;

function keyOf(line) {
	const match = line.match(keyLineRegex);

	return match ? JSON.parse(`"${match[2]}"`) : null;
}

for (const target of selected) {
	const raw = fs.readFileSync(target.file, 'utf8');
	const data = JSON.parse(raw);
	const indent = raw.match(/\n(\s+)"/)?.[1] ?? '    ';
	const serialized = indent + JSON.stringify(key) + ': ' + JSON.stringify(target.value) + ',';

	if (key in data) {
		if (data[key] === target.value) {
			console.log(`${target.name}: "${key}" já existe com o mesmo valor — nada a fazer`);

			continue;
		}

		if (!update) {
			console.log(`${target.name}: "${key}" já existe com ${JSON.stringify(data[key])}. Para substituir use UPDATE=1`);

			continue;
		}

		const lines = raw.split('\n');

		for (let i = 0; i < lines.length; i++) {
			if (keyOf(lines[i]) === key) {
				lines[i] = serialized;
				break;
			}
		}

		fs.writeFileSync(target.file, lines.join('\n'), 'utf8');
		JSON.parse(fs.readFileSync(target.file, 'utf8'));
		console.log(`${target.name}: ATUALIZADO "${key}" = ${JSON.stringify(target.value)}`);

		continue;
	}

	const lines = raw.split('\n');
	let inserted = false;

	for (let i = 0; i < lines.length; i++) {
		const currentKey = keyOf(lines[i]);

		if (currentKey === null) {
			continue;
		}

		if (currentKey.toLowerCase() > key.toLowerCase()) {
			lines.splice(i, 0, serialized);
			inserted = true;
			break;
		}
	}

	if (!inserted) {
		lines.splice(lines.lastIndexOf('}'), 0, serialized);
	}

	fs.writeFileSync(target.file, lines.join('\n'), 'utf8');
	JSON.parse(fs.readFileSync(target.file, 'utf8'));
	console.log(`${target.name}: ADICIONADO "${key}" = ${JSON.stringify(target.value)}`);
}
