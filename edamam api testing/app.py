from flask import Flask, render_template, request, redirect, url_for, session
from edamam_api import analyze_ingredients, autocomplete_ingredient
from edamam_api import search_recipes, get_ingredients_from_recipe_name

app = Flask(__name__)
app.secret_key = 'super-secret-dev-key'
product_cache = {}

@app.route("/home")
def home():
    return render_template("home.html")

@app.route("/")
def index():
    result = session.pop('last_result', None)
    flags = session.pop('last_flags', {})
    ingredients = session.pop('last_ingredients', "")
    product_id = session.pop('last_product_id', "")
    view_link = session.pop('view_link', None)  # ✅ ADD THIS
    lang = request.args.get("lang", "en")
    return render_template("index.html", result=result, flags=flags, lang=lang, product_id=product_id, ingredients=ingredients, view_link=view_link)

@app.route("/analyze", methods=["POST"])
def analyze():
    ingredients_text = request.form.get("ingredients", "")
    lang = request.form.get("lang", "en")
    product_id = request.form.get("product_id", "").strip()
    ingredients = [line.strip() for line in ingredients_text.split("\n") if line.strip()]
    result = analyze_ingredients(ingredients)

    if result["success"]:
        data = result["data"]
        if product_id:
            product_cache[product_id] = data
        session['last_result'] = data
        session['last_flags'] = data["flags"]
        session['last_ingredients'] = ingredients_text
        session['last_product_id'] = product_id
        session['view_link'] = url_for("view_product", product_id=product_id)  # ✅ ADD THIS
        return redirect(url_for("index", lang=lang))
    else:
        return f"<h2>Error occurred</h2><pre>{result['error']}</pre>"


@app.route("/autocomplete", methods=["GET", "POST"])
def autocomplete():
    results = []
    error = None
    query = ""

    if request.method == "POST":
        query = request.form.get("query", "").strip()
        api_result = autocomplete_ingredient(query)
        if api_result["success"]:
            results = api_result["data"]
        else:
            error = api_result["error"]

    return render_template("autocomplete.html", results=results, error=error, query=query)

@app.route("/invalid-test", methods=["GET", "POST"])
def invalid_test():
    error = None
    if request.method == "POST":
        ingredients = ["1 cup unicorn meat", "2 stones of moonlight dust"]
        result = analyze_ingredients(ingredients)
        if result["success"]:
            return render_template("invalid_test.html", result=result["data"], error=None)
        else:
            error = result["error"]
    return render_template("invalid_test.html", result=None, error=error)

@app.route("/recipe-search", methods=["GET", "POST"])
def recipe_search():
    query = ""
    recipes = []
    error = None

    if request.method == "POST":
        query = request.form.get("query", "").strip()
        result = search_recipes(query)
        if result["success"]:
            recipes = result["data"]
        else:
            error = result["error"]

    return render_template("recipe_search.html", query=query, recipes=recipes, error=error)

@app.route("/product/<product_id>")
def view_product(product_id):
    product = product_cache.get(product_id)
    if not product:
        return f"<h2>❌ Product ID '{product_id}' not found</h2><a href='/'>← Back to Analyzer</a>"
    return render_template("product_view.html", product_id=product_id, product=product)

@app.route("/ingredients-from-recipe", methods=["GET", "POST"])
def ingredients_from_recipe():
    recipe_name = ""
    recipe_data = None
    error = None

    if request.method == "POST":
        recipe_name = request.form.get("recipe_name", "").strip()
        result = get_ingredients_from_recipe_name(recipe_name)
        if result["success"]:
            recipe_data = result["data"]
        else:
            error = result["error"]

    return render_template("ingredients_from_recipe.html", 
                         recipe_name=recipe_name, 
                         recipe_data=recipe_data, 
                         error=error)


if __name__ == "__main__":
    print("✅ Flask server is starting at http://127.0.0.1:5000/home")
    app.run(debug=True)
