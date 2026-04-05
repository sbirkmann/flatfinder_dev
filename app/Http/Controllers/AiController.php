<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiController extends Controller
{
    /**
     * Generate or refine content using local Ollama instance.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string',
            'context' => 'nullable|string',
            'history' => 'nullable|array',
            'model' => 'nullable|string',
        ]);

        $model = $request->input('model', 'llama3.1');
        $prompt = $request->input('prompt');
        $context = $request->input('context', '');
        $mode = $request->input('mode', 'html_infoframe');
        $history = $request->input('history', []);

        if ($mode === 'text_description') {
            $systemPrompt = "You are an expert real estate copywriter.\n";
            $systemPrompt .= "Your goal is to help users write compelling property descriptions.\n";
            $systemPrompt .= "CRITICAL RULE: If you provide or update the description text, you MUST wrap it EXACTLY inside one Markdown code block starting with ``` and ending with ```.\n";
            $systemPrompt .= "Do not output HTML, only plain text with linebreaks if needed.\n";
            $systemPrompt .= "If the user just asks a sense, providing only a text answer without backticks is fine.\n";
        } elseif ($mode === 'color_palette') {
            $systemPrompt = "You are a professional UX/UI color specialist for high-end real estate web applications.\n";
            $systemPrompt .= "Your task is to generate five unique, harmonious, and modern color palettes based on the project context.\n";
            $systemPrompt .= "Each palette MUST contain exactly 4 colors: Primary, Primary-Hover, Text-on-Primary, and a subtle Secondary/Border color.\n";
            $systemPrompt .= "Output your response ONLY as a valid JSON array of objects. Each object should have a 'name' (thematic, e.g., 'Urban Elegance') and 'colors' (an array of 4 Hex codes).\n";
            $systemPrompt .= "No explanations, no markdown blocks, just the raw JSON array.\n";
        } else {
            // Context: HTML Infoframe
            $systemPrompt = "You are an expert HTML UI Developer for a real estate platform.\n";
            $systemPrompt .= "Your goal is to help users create 'Infoframes' (HTML snippets) for property details.\n";
            $systemPrompt .= "CRITICAL RULE: If you provide or update HTML code, you MUST wrap it EXACTLY inside one Markdown code block starting with ```html and ending with ```.\n";
            $systemPrompt .= "Do not output raw HTML outside of the ```html code block.\n";
            $systemPrompt .= "If the user just asks a question, providing only a text answer is fine.\n";
        }
        $systemPrompt .= "Always be professional and helpful.\n\n";

        if (!empty($context)) {
            $systemPrompt .= "Current Infoframe HTML Code: \n" . $context . "\n\n";
        }

        // Build conversational context
        $chatContext = "";
        foreach ($history as $msg) {
            $role = ($msg['role'] === 'user') ? "User" : "Assistant";
            $chatContext .= "{$role}: {$msg['content']}\n";
        }

        $fullPrompt = $systemPrompt . $chatContext . "User: " . $prompt . "\nAssistant:";

        try {
            $response = Http::timeout(90)->post('http://127.0.0.1:11434/api/generate', [
                'model' => $model,
                'prompt' => $fullPrompt,
                'stream' => false,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'content' => $data['response'] ?? '',
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Ollama hat einen Fehler zurückgegeben: ' . $response->status(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Lokal-Modell (Ollama) ist nicht erreichbar. Bitte starte "ollama run llama3.2".',
            ], 200);
        }
    }
}
