/**
 * Webpack configuration for WC Product Sidebar React frontend.
 */

const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = (env, argv) => {
  const isDev = argv.mode === 'development';

  return {
    entry: './src/index.jsx',

    output: {
      path: path.resolve(__dirname, 'build'),
      filename: 'bundle.js',
      clean: true,
    },

    resolve: {
      extensions: ['.jsx', '.js'],
    },

    cache: {
      type: 'filesystem',
      buildDependencies: {
        config: [__filename],
      },
    },

    module: {
      rules: [
        // JSX / JS
        {
          test: /\.(js|jsx)$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              cacheDirectory: true,
              cacheCompression: false,
              presets: [
                ['@babel/preset-env', {
                  targets: '> 1%, last 2 versions, not dead',
                  modules: false,
                }],
                ['@babel/preset-react', {
                  runtime: 'automatic',
                }],
              ],
            },
          },
        },

        // SCSS / CSS
        {
          test: /\.(scss|css)$/,
          use: [
            MiniCssExtractPlugin.loader,
            'css-loader',
            'sass-loader',
          ],
        },
      ],
    },

    plugins: [
      new MiniCssExtractPlugin({
        filename: 'main.css',
      }),
    ],

    optimization: {
      minimize: !isDev,
      minimizer: [
        new TerserPlugin({
          parallel: true,
          extractComments: false,
          terserOptions: {
            compress: {
              drop_console: !isDev,
            },
            format: {
              comments: false,
            },
          },
        }),
        new CssMinimizerPlugin(),
      ],
    },

    // Externalize React from WP if desired (optional)
    // externals: {
    //   react: 'React',
    //   'react-dom': 'ReactDOM',
    // },

    devtool: isDev ? 'source-map' : false,

    performance: {
      hints: isDev ? false : 'warning',
      maxEntrypointSize: 400 * 1024,
      maxAssetSize: 400 * 1024,
    },
  };
};
