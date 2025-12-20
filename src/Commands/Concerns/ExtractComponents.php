<?php

declare(strict_types=1);

namespace Laravilt\Panel\Commands\Concerns;

/**
 * Methods for extracting form/table/infolist from resources.
 */
trait ExtractComponents
{
    protected function hasFormMethod(string $content): bool
    {
        return (bool) preg_match('/public\s+static\s+function\s+form\s*\(/s', $content);
    }

    protected function hasTableMethod(string $content): bool
    {
        return (bool) preg_match('/public\s+static\s+function\s+table\s*\(/s', $content);
    }

    protected function hasInfolistMethod(string $content): bool
    {
        return (bool) preg_match('/public\s+static\s+function\s+infolist\s*\(/s', $content);
    }

    /**
     * Extract the form method content and create a standalone Form class.
     */
    protected function extractFormClass(string $originalContent, string $convertedContent, string $resourceName, string $baseNamespace, string $sourcePath): string
    {
        // Check if the form method delegates to a class-based structure
        $delegatedClass = $this->getDelegatedClassName($originalContent, 'form');

        if ($delegatedClass) {
            // Find the class file
            $classFilePath = $this->findClassBasedStructureFile($delegatedClass, $sourcePath, 'form');

            if ($classFilePath) {
                // Read and extract content from the class file
                $formBody = $this->extractClassBasedMethodBody($classFilePath);
                $useStatements = $this->extractClassBasedUseStatements($classFilePath);

                // Convert the content (use statements and body)
                $useStatements = $this->convertUseStatements($useStatements);
                $formBody = $this->convertUseStatements($formBody);
                $formBody = $this->convertIcons($formBody);
                $formBody = $this->convertBackedEnum($formBody);
                $formBody = $this->convertMethodsAndProperties($formBody);
            } else {
                // Class file not found, return empty
                return '';
            }
        } else {
            // Extract the form method body from the converted content
            $formBody = $this->extractMethodBody($convertedContent, 'form');

            if (empty($formBody)) {
                return '';
            }

            // Extract use statements that are relevant for forms
            $useStatements = $this->extractRelevantUseStatements($convertedContent, $formBody);
        }

        // Remove any use statement for Schema since we add it explicitly
        $useStatements = preg_replace('/^use\s+Laravilt\\\\Schemas\\\\Schema;\s*\n?/m', '', $useStatements);
        $useStatements = trim($useStatements);

        // Check if Get or Set are used in the form body
        $additionalUses = '';
        if (preg_match('/\bGet\s+\$get\b/', $formBody) && ! preg_match('/use\s+Laravilt\\\\Support\\\\Utilities\\\\Get;/', $useStatements)) {
            $additionalUses .= "use Laravilt\\Support\\Utilities\\Get;\n";
        }
        if (preg_match('/\bSet\s+\$set\b/', $formBody) && ! preg_match('/use\s+Laravilt\\\\Support\\\\Utilities\\\\Set;/', $useStatements)) {
            $additionalUses .= "use Laravilt\\Support\\Utilities\\Set;\n";
        }

        $formClass = <<<PHP
<?php

namespace {$baseNamespace}\\Form;

{$useStatements}
{$additionalUses}use Laravilt\\Schemas\\Schema;

class {$resourceName}Form
{
    public static function make(Schema \$schema): Schema
    {
        {$formBody}
    }
}
PHP;

        // Clean up multiple blank lines
        $formClass = preg_replace('/\n{3,}/', "\n\n", $formClass);

        return $formClass;
    }

    /**
     * Extract the table method content and create a standalone Table class.
     */
    protected function extractTableClass(string $originalContent, string $convertedContent, string $resourceName, string $baseNamespace, string $sourcePath): string
    {
        // Check if the table method delegates to a class-based structure
        $delegatedClass = $this->getDelegatedClassName($originalContent, 'table');

        if ($delegatedClass) {
            // Find the class file
            $classFilePath = $this->findClassBasedStructureFile($delegatedClass, $sourcePath, 'table');

            if ($classFilePath) {
                // Read and extract content from the class file
                $tableBody = $this->extractClassBasedMethodBody($classFilePath);
                $useStatements = $this->extractClassBasedUseStatements($classFilePath);

                // Convert the content (use statements and body)
                $useStatements = $this->convertUseStatements($useStatements);
                $tableBody = $this->convertUseStatements($tableBody);
                $tableBody = $this->convertIcons($tableBody);
                $tableBody = $this->convertBackedEnum($tableBody);
                $tableBody = $this->convertMethodsAndProperties($tableBody);
            } else {
                // Class file not found, return empty
                return '';
            }
        } else {
            // Extract the table method body from the converted content
            $tableBody = $this->extractMethodBody($convertedContent, 'table');

            if (empty($tableBody)) {
                return '';
            }

            // Extract use statements that are relevant for tables
            $useStatements = $this->extractRelevantUseStatements($convertedContent, $tableBody);
        }

        // Remove any use statement for Table since we add it explicitly
        $useStatements = preg_replace('/^use\s+Laravilt\\\\Tables\\\\Table;\s*\n?/m', '', $useStatements);
        $useStatements = trim($useStatements);

        // Check if Get or Set are used in the table body
        $additionalUses = '';
        if (preg_match('/\bGet\s+\$get\b/', $tableBody) && ! preg_match('/use\s+Laravilt\\\\Support\\\\Utilities\\\\Get;/', $useStatements)) {
            $additionalUses .= "use Laravilt\\Support\\Utilities\\Get;\n";
        }
        if (preg_match('/\bSet\s+\$set\b/', $tableBody) && ! preg_match('/use\s+Laravilt\\\\Support\\\\Utilities\\\\Set;/', $useStatements)) {
            $additionalUses .= "use Laravilt\\Support\\Utilities\\Set;\n";
        }

        $tableClass = <<<PHP
<?php

namespace {$baseNamespace}\\Table;

{$useStatements}
{$additionalUses}use Laravilt\\Tables\\Table;

class {$resourceName}Table
{
    public static function make(Table \$table): Table
    {
        {$tableBody}
    }
}
PHP;

        // Clean up multiple blank lines
        $tableClass = preg_replace('/\n{3,}/', "\n\n", $tableClass);

        return $tableClass;
    }

    /**
     * Extract the infolist method content and create a standalone Infolist class.
     */
    protected function extractInfolistClass(string $originalContent, string $convertedContent, string $resourceName, string $baseNamespace, string $sourcePath): string
    {
        // Check if the infolist method delegates to a class-based structure
        $delegatedClass = $this->getDelegatedClassName($originalContent, 'infolist');

        if ($delegatedClass) {
            // Find the class file
            $classFilePath = $this->findClassBasedStructureFile($delegatedClass, $sourcePath, 'infolist');

            if ($classFilePath) {
                // Read and extract content from the class file
                $infolistBody = $this->extractClassBasedMethodBody($classFilePath);
                $useStatements = $this->extractClassBasedUseStatements($classFilePath);

                // Convert the content (use statements and body)
                $useStatements = $this->convertUseStatements($useStatements);
                $infolistBody = $this->convertUseStatements($infolistBody);
                $infolistBody = $this->convertIcons($infolistBody);
                $infolistBody = $this->convertBackedEnum($infolistBody);
                $infolistBody = $this->convertMethodsAndProperties($infolistBody);
            } else {
                // Class file not found, return empty
                return '';
            }
        } else {
            // Extract the infolist method body from the converted content
            $infolistBody = $this->extractMethodBody($convertedContent, 'infolist');

            if (empty($infolistBody)) {
                return '';
            }

            // Extract use statements that are relevant for infolists
            $useStatements = $this->extractRelevantUseStatements($convertedContent, $infolistBody);
        }

        // Remove any use statement for Schema since we add it explicitly
        $useStatements = preg_replace('/^use\s+Laravilt\\\\Schemas\\\\Schema;\s*\n?/m', '', $useStatements);
        $useStatements = preg_replace('/^use\s+Filament\\\\Schemas\\\\Schema;\s*\n?/m', '', $useStatements);
        $useStatements = trim($useStatements);

        // Check if Get or Set are used in the infolist body
        $additionalUses = '';
        if (preg_match('/\bGet\s+\$get\b/', $infolistBody) && ! preg_match('/use\s+Laravilt\\\\Support\\\\Utilities\\\\Get;/', $useStatements)) {
            $additionalUses .= "use Laravilt\\Support\\Utilities\\Get;\n";
        }
        if (preg_match('/\bSet\s+\$set\b/', $infolistBody) && ! preg_match('/use\s+Laravilt\\\\Support\\\\Utilities\\\\Set;/', $useStatements)) {
            $additionalUses .= "use Laravilt\\Support\\Utilities\\Set;\n";
        }

        // Laravilt uses Schema for infolists (same as Filament v4)
        $infolistClass = <<<PHP
<?php

namespace {$baseNamespace}\\Infolist;

{$useStatements}
{$additionalUses}use Laravilt\\Schemas\\Schema;

class {$resourceName}Infolist
{
    public static function make(Schema \$schema): Schema
    {
        {$infolistBody}
    }
}
PHP;

        // Clean up multiple blank lines
        $infolistClass = preg_replace('/\n{3,}/', "\n\n", $infolistClass);

        return $infolistClass;
    }

    /**
     * Extract the body of a method.
     */
    protected function extractMethodBody(string $content, string $methodName): string
    {
        // Pattern to match the entire method including its body
        $pattern = '/public\s+static\s+function\s+'.$methodName.'\s*\([^)]*\)\s*:\s*\w+\s*\{/s';

        if (! preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $startPos = $matches[0][1] + strlen($matches[0][0]);
        $braceCount = 1;
        $pos = $startPos;
        $len = strlen($content);

        // Find the matching closing brace
        while ($pos < $len && $braceCount > 0) {
            $char = $content[$pos];
            if ($char === '{') {
                $braceCount++;
            } elseif ($char === '}') {
                $braceCount--;
            }
            $pos++;
        }

        $body = substr($content, $startPos, $pos - $startPos - 1);
        $body = trim($body);

        return $body;
    }

    /**
     * Check if a method body delegates to a class-based structure.
     *
     * @return string|null The class name if it delegates, null otherwise
     */
    protected function getDelegatedClassName(string $content, string $methodName): ?string
    {
        $body = $this->extractMethodBody($content, $methodName);

        // Match patterns like "return CustomerForm::configure($schema);" or "return CustomerTable::make($table);"
        if (preg_match('/^\s*return\s+(\w+(?:Form|Table|Infolist))::(?:configure|make)\s*\(\s*\$\w+\s*\)\s*;?\s*$/s', $body, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Find the source file for a class-based structure.
     *
     * @param  string  $className  The class name (e.g., CustomerForm)
     * @param  string  $resourcePath  The resource file path
     * @param  string  $type  The type (form, table, infolist)
     * @return string|null The file path if found, null otherwise
     */
    protected function findClassBasedStructureFile(string $className, string $resourcePath, string $type): ?string
    {
        $resourceDir = dirname($resourcePath);

        // Common locations for class-based structures
        $possiblePaths = [
            // Direct subfolder (Form/, Table/, Infolist/)
            "{$resourceDir}/".ucfirst($type)."/{$className}.php",
            // Schemas folder (for forms in v4)
            "{$resourceDir}/Schemas/{$className}.php",
            // Tables folder
            "{$resourceDir}/Tables/{$className}.php",
            // Infolists folder
            "{$resourceDir}/Infolists/{$className}.php",
            // Forms folder
            "{$resourceDir}/Forms/{$className}.php",
            // Same directory as resource
            "{$resourceDir}/{$className}.php",
        ];

        foreach ($possiblePaths as $path) {
            if ($this->files->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Extract the method body from a class-based structure file.
     *
     * @param  string  $filePath  The path to the class file
     * @param  string  $methodName  The method name to extract (e.g., 'configure', 'make')
     * @return string The method body content
     */
    protected function extractClassBasedMethodBody(string $filePath, string $methodName = 'configure'): string
    {
        $content = $this->files->get($filePath);

        // Try multiple method names that class-based structures might use
        $methodNames = ['configure', 'make', 'schema', 'columns', 'entries'];

        foreach ($methodNames as $name) {
            $body = $this->extractMethodBodyFromClassFile($content, $name);
            if (! empty($body)) {
                return $body;
            }
        }

        return '';
    }

    /**
     * Extract method body from a class file content.
     */
    protected function extractMethodBodyFromClassFile(string $content, string $methodName): string
    {
        // Pattern to match static or non-static methods
        $pattern = '/public\s+(?:static\s+)?function\s+'.$methodName.'\s*\([^)]*\)(?:\s*:\s*\w+)?\s*\{/s';

        if (! preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $startPos = $matches[0][1] + strlen($matches[0][0]);
        $braceCount = 1;
        $pos = $startPos;
        $len = strlen($content);

        // Find the matching closing brace
        while ($pos < $len && $braceCount > 0) {
            $char = $content[$pos];
            if ($char === '{') {
                $braceCount++;
            } elseif ($char === '}') {
                $braceCount--;
            }
            $pos++;
        }

        return trim(substr($content, $startPos, $pos - $startPos - 1));
    }

    /**
     * Extract use statements from a class-based structure file.
     */
    protected function extractClassBasedUseStatements(string $filePath): string
    {
        $content = $this->files->get($filePath);
        preg_match_all('/^use\s+[^;]+;$/m', $content, $matches);

        return implode("\n", $matches[0] ?? []);
    }

    /**
     * Extract relevant use statements for a method body.
     */
    protected function extractRelevantUseStatements(string $content, string $methodBody): string
    {
        preg_match_all('/^use\s+([^;]+);$/m', $content, $matches);

        $relevantUses = [];
        foreach ($matches[1] as $use) {
            $className = \Illuminate\Support\Str::afterLast($use, '\\');
            $alias = $className;

            // Check if there's an alias
            if (\Illuminate\Support\Str::contains($use, ' as ')) {
                [$use, $alias] = explode(' as ', $use);
                $alias = trim($alias);
            }

            // Check if this class is used in the method body
            if (\Illuminate\Support\Str::contains($methodBody, $alias)) {
                $relevantUses[] = "use {$use}".(\Illuminate\Support\Str::contains($matches[0][array_search($use, $matches[1])], ' as ') ? " as {$alias}" : '').';';
            }
        }

        return implode("\n", array_unique($relevantUses));
    }

    /**
     * Find the end position of a method by counting braces.
     */
    protected function findMethodEnd(string $content, int $startPos): int
    {
        $len = strlen($content);
        $braceCount = 0;
        $pos = $startPos;
        $inMethod = false;

        while ($pos < $len) {
            $char = $content[$pos];

            if ($char === '{') {
                $braceCount++;
                $inMethod = true;
            } elseif ($char === '}') {
                $braceCount--;
                if ($inMethod && $braceCount === 0) {
                    return $pos + 1; // Include the closing brace
                }
            }
            $pos++;
        }

        return $pos;
    }

    /**
     * Replace the form method with a call to the Form class.
     */
    protected function replaceFormMethodWithClass(string $content, string $resourceName, string $baseNamespace): string
    {
        // Add use statement for the Form class
        $formUse = "use {$baseNamespace}\\Form\\{$resourceName}Form;";
        if (! \Illuminate\Support\Str::contains($content, $formUse)) {
            $content = preg_replace(
                '/(namespace\s+[^;]+;\s*\n)/',
                "$1\n{$formUse}\n",
                $content
            );
        }

        // Find the form method and replace it using brace counting
        $pattern = '/public\s+static\s+function\s+form\s*\([^)]*\)\s*:\s*Schema\s*/s';
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $methodStart = $matches[0][1];
            $methodEnd = $this->findMethodEnd($content, $methodStart);

            $replacement = <<<PHP
public static function form(Schema \$schema): Schema
    {
        return {$resourceName}Form::make(\$schema);
    }
PHP;

            $content = substr($content, 0, $methodStart).$replacement.substr($content, $methodEnd);
        }

        return $content;
    }

    /**
     * Replace the table method with a call to the Table class.
     */
    protected function replaceTableMethodWithClass(string $content, string $resourceName, string $baseNamespace): string
    {
        // Add use statement for the Table class
        $tableUse = "use {$baseNamespace}\\Table\\{$resourceName}Table;";
        if (! \Illuminate\Support\Str::contains($content, $tableUse)) {
            $content = preg_replace(
                '/(namespace\s+[^;]+;\s*\n)/',
                "$1\n{$tableUse}\n",
                $content
            );
        }

        // Find the table method and replace it using brace counting
        $pattern = '/public\s+static\s+function\s+table\s*\([^)]*\)\s*:\s*Table\s*/s';
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $methodStart = $matches[0][1];
            $methodEnd = $this->findMethodEnd($content, $methodStart);

            $replacement = <<<PHP
public static function table(Table \$table): Table
    {
        return {$resourceName}Table::make(\$table);
    }
PHP;

            $content = substr($content, 0, $methodStart).$replacement.substr($content, $methodEnd);
        }

        return $content;
    }

    /**
     * Replace the infolist method with a call to the Infolist class.
     */
    protected function replaceInfolistMethodWithClass(string $content, string $resourceName, string $baseNamespace): string
    {
        // Add use statement for the Infolist class
        $infolistUse = "use {$baseNamespace}\\Infolist\\{$resourceName}Infolist;";
        if (! \Illuminate\Support\Str::contains($content, $infolistUse)) {
            $content = preg_replace(
                '/(namespace\s+[^;]+;\s*\n)/',
                "$1\n{$infolistUse}\n",
                $content
            );
        }

        // Find the infolist method and replace it using brace counting
        // Laravilt uses Schema for infolists (same as Filament v4)
        $pattern = '/public\s+static\s+function\s+infolist\s*\([^)]*\)\s*:\s*Schema\s*/s';
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $methodStart = $matches[0][1];
            $methodEnd = $this->findMethodEnd($content, $methodStart);

            $replacement = <<<PHP
public static function infolist(Schema \$schema): Schema
    {
        return {$resourceName}Infolist::make(\$schema);
    }
PHP;

            $content = substr($content, 0, $methodStart).$replacement.substr($content, $methodEnd);
        }

        return $content;
    }
}
