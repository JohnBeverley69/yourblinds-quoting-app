<?php
declare(strict_types=1);

/**
 * Formula engine — an Excel-style expression evaluator for the manufacturing
 * build rules (deductions, cut sizes, vane/truck counts).
 *
 * Evaluates expressions such as:
 *   IF(ExactorRecess="Recess", Width-12, IF(ExactorRecess="Exact", Width-2, 0))
 *   EVN(ROUN_UP((Width*10)/77))
 * over a named-variable context.
 *
 * Supports: IF / AND / OR / NOT / ROUND / ROUN_UP(=ROUNDUP) / ROUNDDOWN /
 * FIND / EVN(=EVEN) / MAX / MIN / LOOKUP / BESTFIT; arithmetic (+ - * /), string concat (&),
 * comparisons (= <> < > <= >=), numbers, "quoted strings", and variables
 * (case-insensitive). Rebuilds Blind Matrix's formula behaviour behind a
 * validating engine — unknown variables/functions and bad syntax throw a
 * FormulaError (which is exactly what powers the build-rules test panel).
 *
 *   formula_eval("Width-12", ['Width' => 1200])  →  1188.0
 *
 * Pure; no DB. Safe to require more than once.
 */

if (!class_exists('FormulaError')) {
    final class FormulaError extends RuntimeException {}
}

if (!class_exists('FormulaEngine')) {
    final class FormulaEngine
    {
        /** @var array<int,array{0:string,1:string}> */
        private array $toks;
        private int $pos = 0;
        /** @var array<string,mixed> lower-cased variable map */
        private array $vars;
        /** @var array<string,array<string,float>> lower-cased allowance tables: name => (key_norm => value) */
        private array $allowances;

        private function __construct(string $expr, array $vars, array $allowances)
        {
            $this->toks = self::tokenize($expr);
            $this->vars = [];
            foreach ($vars as $k => $v) {
                $this->vars[strtolower((string) $k)] = $v;
            }
            $this->allowances = [];
            foreach ($allowances as $name => $rows) {
                $this->allowances[strtolower((string) $name)] = $rows;
            }
        }

        /**
         * Evaluate $expr against $vars. Returns float|string|bool.
         *
         * $allowances feeds LOOKUP(): a map of tableName => (key_norm => value),
         * where key_norm is the lower-cased, "|"-joined key columns.
         */
        public static function evaluate(string $expr, array $vars = [], array $allowances = [])
        {
            $expr = trim($expr);
            if ($expr === '') {
                throw new FormulaError('Empty formula.');
            }
            // A leading "=" (spreadsheet habit) is allowed and stripped.
            if ($expr[0] === '=') {
                $expr = ltrim(substr($expr, 1));
            }
            $engine = new self($expr, $vars, $allowances);
            $ast = $engine->parseExpr();
            if ($engine->pos < count($engine->toks)) {
                $t = $engine->toks[$engine->pos];
                throw new FormulaError("Unexpected '{$t[1]}' in formula.");
            }
            return $engine->ev($ast);
        }

        // ---- Tokenizer ---------------------------------------------------
        /** @return array<int,array{0:string,1:string}> [type, value] */
        private static function tokenize(string $s): array
        {
            $t = [];
            $n = strlen($s);
            $i = 0;
            while ($i < $n) {
                $c = $s[$i];
                if (ctype_space($c)) { $i++; continue; }

                // number
                if (ctype_digit($c) || ($c === '.' && $i + 1 < $n && ctype_digit($s[$i + 1]))) {
                    $j = $i;
                    while ($j < $n && (ctype_digit($s[$j]) || $s[$j] === '.')) $j++;
                    $t[] = ['num', substr($s, $i, $j - $i)];
                    $i = $j;
                    continue;
                }
                // string  "..."  ("" is a literal quote)
                if ($c === '"') {
                    $j = $i + 1; $buf = '';
                    while ($j < $n) {
                        if ($s[$j] === '"') {
                            if ($j + 1 < $n && $s[$j + 1] === '"') { $buf .= '"'; $j += 2; continue; }
                            $j++; break;
                        }
                        $buf .= $s[$j]; $j++;
                    }
                    $t[] = ['str', $buf];
                    $i = $j;
                    continue;
                }
                // identifier (function or variable)
                if (ctype_alpha($c) || $c === '_') {
                    $j = $i;
                    while ($j < $n && (ctype_alnum($s[$j]) || $s[$j] === '_')) $j++;
                    $t[] = ['ident', substr($s, $i, $j - $i)];
                    $i = $j;
                    continue;
                }
                // two-char operators
                $two = substr($s, $i, 2);
                if ($two === '<=' || $two === '>=' || $two === '<>') {
                    $t[] = ['op', $two]; $i += 2; continue;
                }
                // single-char
                if (strpos('+-*/&=<>(),', $c) !== false) {
                    $type = ($c === '(') ? 'lparen' : (($c === ')') ? 'rparen' : (($c === ',') ? 'comma' : 'op'));
                    $t[] = [$type, $c];
                    $i++;
                    continue;
                }
                throw new FormulaError("Unexpected character '{$c}' in formula.");
            }
            return $t;
        }

        // ---- Parser (recursive descent → AST) ----------------------------
        private function peek(): ?array { return $this->toks[$this->pos] ?? null; }
        private function next(): ?array { return $this->toks[$this->pos++] ?? null; }
        private function isOp(string $v): bool
        {
            $t = $this->peek();
            return $t !== null && $t[0] === 'op' && $t[1] === $v;
        }

        private function parseExpr(): array { return $this->parseComparison(); }

        private function parseComparison(): array
        {
            $left = $this->parseConcat();
            while (true) {
                $t = $this->peek();
                if ($t !== null && $t[0] === 'op' && in_array($t[1], ['=', '<>', '<', '>', '<=', '>='], true)) {
                    $this->next();
                    $right = $this->parseConcat();
                    $left = ['binop', $t[1], $left, $right];
                } else break;
            }
            return $left;
        }

        private function parseConcat(): array
        {
            $left = $this->parseAdditive();
            while ($this->isOp('&')) { $this->next(); $left = ['binop', '&', $left, $this->parseAdditive()]; }
            return $left;
        }

        private function parseAdditive(): array
        {
            $left = $this->parseMultiplicative();
            while ($this->isOp('+') || $this->isOp('-')) {
                $op = $this->next()[1];
                $left = ['binop', $op, $left, $this->parseMultiplicative()];
            }
            return $left;
        }

        private function parseMultiplicative(): array
        {
            $left = $this->parseUnary();
            while ($this->isOp('*') || $this->isOp('/')) {
                $op = $this->next()[1];
                $left = ['binop', $op, $left, $this->parseUnary()];
            }
            return $left;
        }

        private function parseUnary(): array
        {
            if ($this->isOp('-')) { $this->next(); return ['unary', '-', $this->parseUnary()]; }
            if ($this->isOp('+')) { $this->next(); return $this->parseUnary(); }
            return $this->parsePrimary();
        }

        private function parsePrimary(): array
        {
            $t = $this->next();
            if ($t === null) throw new FormulaError('Unexpected end of formula.');

            if ($t[0] === 'num') return ['num', (float) $t[1]];
            if ($t[0] === 'str') return ['str', $t[1]];
            if ($t[0] === 'lparen') {
                $e = $this->parseExpr();
                $close = $this->next();
                if ($close === null || $close[0] !== 'rparen') throw new FormulaError('Missing ")" in formula.');
                return $e;
            }
            if ($t[0] === 'ident') {
                // function call?
                $nt = $this->peek();
                if ($nt !== null && $nt[0] === 'lparen') {
                    $this->next(); // consume '('
                    $args = [];
                    if (!($this->peek() !== null && $this->peek()[0] === 'rparen')) {
                        $args[] = $this->parseExpr();
                        while ($this->peek() !== null && $this->peek()[0] === 'comma') {
                            $this->next();
                            $args[] = $this->parseExpr();
                        }
                    }
                    $close = $this->next();
                    if ($close === null || $close[0] !== 'rparen') throw new FormulaError('Missing ")" after ' . $t[1] . '(...).');
                    return ['call', strtolower($t[1]), $args];
                }
                return ['var', $t[1]];
            }
            throw new FormulaError("Unexpected '{$t[1]}' in formula.");
        }

        // ---- Evaluator ---------------------------------------------------
        private function ev(array $node)
        {
            switch ($node[0]) {
                case 'num': return $node[1];
                case 'str': return $node[1];
                case 'var':
                    $key = strtolower($node[1]);
                    if (!array_key_exists($key, $this->vars)) {
                        throw new FormulaError("Unknown variable: {$node[1]}");
                    }
                    return $this->vars[$key];
                case 'unary':
                    return -self::num($this->ev($node[2]));
                case 'binop':
                    return $this->evBin($node[1], $node[2], $node[3]);
                case 'call':
                    return $this->evCall($node[1], $node[2]);
            }
            throw new FormulaError('Bad expression.');
        }

        private function evBin(string $op, array $l, array $r)
        {
            if (in_array($op, ['=', '<>', '<', '>', '<=', '>='], true)) {
                return self::compare($this->ev($l), $op, $this->ev($r));
            }
            if ($op === '&') {
                return self::str($this->ev($l)) . self::str($this->ev($r));
            }
            $a = self::num($this->ev($l));
            $b = self::num($this->ev($r));
            switch ($op) {
                case '+': return $a + $b;
                case '-': return $a - $b;
                case '*': return $a * $b;
                case '/':
                    if ($b == 0.0) throw new FormulaError('Division by zero.');
                    return $a / $b;
            }
            throw new FormulaError("Unknown operator {$op}.");
        }

        private function evCall(string $fn, array $args)
        {
            switch ($fn) {
                case 'if':
                    if (count($args) < 2) throw new FormulaError('IF needs 2 or 3 arguments.');
                    return self::truthy($this->ev($args[0]))
                        ? $this->ev($args[1])
                        : (isset($args[2]) ? $this->ev($args[2]) : false);
                case 'and':
                    foreach ($args as $a) { if (!self::truthy($this->ev($a))) return false; }
                    return true;
                case 'or':
                    foreach ($args as $a) { if (self::truthy($this->ev($a))) return true; }
                    return false;
                case 'not':
                    return !self::truthy($this->ev($args[0]));
                case 'round':
                    $x = self::num($this->ev($args[0]));
                    $d = isset($args[1]) ? (int) self::num($this->ev($args[1])) : 0;
                    return round($x, $d);
                case 'roun_up': case 'roundup':
                    $x = self::num($this->ev($args[0]));
                    if (isset($args[1])) {
                        $m = pow(10, (int) self::num($this->ev($args[1])));
                        return ($x < 0 ? -1 : 1) * ceil(abs($x) * $m) / $m;
                    }
                    return (float) ceil($x);
                case 'roun_down': case 'rounddown':
                    $x = self::num($this->ev($args[0]));
                    if (isset($args[1])) {
                        $m = pow(10, (int) self::num($this->ev($args[1])));
                        return ($x < 0 ? -1 : 1) * floor(abs($x) * $m) / $m;
                    }
                    return (float) floor($x);
                case 'evn': case 'even':
                    $x = self::num($this->ev($args[0]));
                    $k = (int) ceil(abs($x));
                    if ($k % 2 !== 0) $k++;
                    return (float) ($x < 0 ? -$k : $k);
                case 'find':
                    $needle = self::str($this->ev($args[0]));
                    $hay    = self::str($this->ev($args[1]));
                    if ($needle === '') return 1.0;
                    $p = strpos($hay, $needle);   // FIND is case-sensitive, like Excel
                    return $p === false ? 0.0 : (float) ($p + 1);
                case 'max':
                    $vals = array_map(fn ($a) => self::num($this->ev($a)), $args);
                    return $vals ? (float) max($vals) : 0.0;
                case 'min':
                    $vals = array_map(fn ($a) => self::num($this->ev($a)), $args);
                    return $vals ? (float) min($vals) : 0.0;
                case 'lookup':
                    // LOOKUP("table", key1, key2, ...) → the value whose key columns
                    // match (case-insensitive). Shared allowance tables so a headrail
                    // deduction is one lookup, not a 44-branch IF.
                    if (count($args) < 2) throw new FormulaError('LOOKUP needs a table name and at least one key.');
                    $name = strtolower(trim(self::str($this->ev($args[0]))));
                    $keys = [];
                    for ($i = 1, $c = count($args); $i < $c; $i++) {
                        $keys[] = strtolower(trim(self::str($this->ev($args[$i]))));
                    }
                    $keyNorm = implode('|', $keys);
                    if (!isset($this->allowances[$name])) {
                        throw new FormulaError("Unknown allowance table: {$name}");
                    }
                    if (!array_key_exists($keyNorm, $this->allowances[$name])) {
                        throw new FormulaError("No allowance in \"{$name}\" for: " . implode(', ', $keys));
                    }
                    return (float) $this->allowances[$name][$keyNorm];
                case 'bestfit':
                    // BESTFIT("table", value, part) → the best-fit row for a value:
                    // the row with the SMALLEST table value that is still >= value,
                    // returning its key component #part (1-based). Powers Louvolite's
                    // truck tables, where each row's key is "count|size" and its value
                    // is the max width covered — so BESTFIT("vogue_ow_cord", Width, 1)
                    // is the truck count and part 2 is the truck size, both from the
                    // one combination with least oversail. Ties break to the smaller
                    // count, then the smaller size. No covering row throws (too wide).
                    if (count($args) < 3) throw new FormulaError('BESTFIT needs a table name, a value, and a key position.');
                    $name   = strtolower(trim(self::str($this->ev($args[0]))));
                    $target = self::num($this->ev($args[1]));
                    $part   = (int) self::num($this->ev($args[2]));
                    if (!isset($this->allowances[$name])) {
                        throw new FormulaError("Unknown allowance table: {$name}");
                    }
                    $bestKey = null; $bestVal = null;
                    foreach ($this->allowances[$name] as $k => $v) {
                        $v = (float) $v;
                        if ($v < $target) continue;
                        $better = false;
                        if ($bestVal === null || $v < $bestVal) {
                            $better = true;
                        } elseif ($v == $bestVal) {
                            $cur  = array_map('floatval', explode('|', (string) $k));
                            $prev = array_map('floatval', explode('|', (string) $bestKey));
                            for ($z = 0, $zc = min(count($cur), count($prev)); $z < $zc; $z++) {
                                if ($cur[$z] < $prev[$z]) { $better = true; break; }
                                if ($cur[$z] > $prev[$z]) break;
                            }
                        }
                        if ($better) { $bestVal = $v; $bestKey = (string) $k; }
                    }
                    if ($bestKey === null) {
                        throw new FormulaError("No best-fit in \"{$name}\" covers " . self::str($target) . ".");
                    }
                    $parts = explode('|', $bestKey);
                    if ($part < 1 || $part > count($parts)) {
                        throw new FormulaError("BESTFIT key position {$part} out of range for \"{$name}\".");
                    }
                    $val = $parts[$part - 1];
                    return is_numeric($val) ? (float) $val : $val;
            }
            throw new FormulaError("Unknown function: {$fn}()");
        }

        // ---- Coercion helpers -------------------------------------------
        private static function truthy($v): bool
        {
            if (is_bool($v)) return $v;
            if (is_int($v) || is_float($v)) return $v != 0;
            if (is_string($v)) { $t = strtolower(trim($v)); return $t !== '' && $t !== 'false' && $t !== '0'; }
            return false;
        }

        private static function num($v): float
        {
            if (is_bool($v)) return $v ? 1.0 : 0.0;
            if (is_int($v) || is_float($v)) return (float) $v;
            if (is_string($v)) {
                $t = trim($v);
                if ($t === '') return 0.0;
                if (is_numeric($t)) return (float) $t;
                throw new FormulaError("Cannot use text \"{$v}\" as a number.");
            }
            return 0.0;
        }

        private static function str($v): string
        {
            if (is_bool($v)) return $v ? 'TRUE' : 'FALSE';
            if (is_float($v)) {
                if ($v == floor($v) && abs($v) < 1e15) return (string) (int) $v;
                return rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');
            }
            return (string) $v;
        }

        private static function compare($a, string $op, $b): bool
        {
            $numish = static fn ($x) => is_int($x) || is_float($x) || is_bool($x) || (is_string($x) && $x !== '' && is_numeric(trim($x)));
            if ($numish($a) && $numish($b)) {
                $x = self::num($a); $y = self::num($b);
            } else {
                $x = strtolower(self::str($a)); $y = strtolower(self::str($b));
            }
            switch ($op) {
                case '=':  return $x == $y;
                case '<>': return $x != $y;
                case '<':  return $x <  $y;
                case '>':  return $x >  $y;
                case '<=': return $x <= $y;
                case '>=': return $x >= $y;
            }
            throw new FormulaError("Unknown comparison {$op}.");
        }
    }
}

if (!function_exists('formula_eval')) {
    /** Convenience wrapper. Returns float|string|bool, or throws FormulaError. */
    function formula_eval(string $expr, array $vars = [], array $allowances = [])
    {
        return FormulaEngine::evaluate($expr, $vars, $allowances);
    }
}
