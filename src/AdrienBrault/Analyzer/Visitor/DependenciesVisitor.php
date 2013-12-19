<?php

namespace AdrienBrault\Analyzer\Visitor;

use PHPParser_Node;
use PHPParser_Node_Expr_ClassConstFetch;
use PHPParser_Node_Expr_Instanceof;
use PHPParser_Node_Expr_New;
use PHPParser_Node_Expr_StaticCall;
use PHPParser_Node_Expr_StaticPropertyFetch;
use PHPParser_Node_Name;
use PHPParser_Node_Param;
use PHPParser_Node_Stmt_Catch;
use PHPParser_Node_Stmt_Class;
use PHPParser_Node_Stmt_Interface;
use PHPParser_NodeVisitorAbstract;

class DependenciesVisitor extends PHPParser_NodeVisitorAbstract
{
    /**
     * @var string
     */
    private $current;

    private $dependencies = array();

    /**
     * @return array
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }

    public function enterNode(PHPParser_Node $node)
    {
        if ($node instanceof PHPParser_Node_Stmt_Class || $node instanceof PHPParser_Node_Stmt_Interface) {
            $this->current = (string) $node->namespacedName;
            $this->dependencies[$this->current] = array();
        }

        if (null === $this->current) {
            return;
        }

        if ($node instanceof PHPParser_Node_Stmt_Class || $node instanceof PHPParser_Node_Stmt_Interface) {
            $this->addDependency($node->namespacedName);
        }

        if ($node instanceof PHPParser_Node_Stmt_Class) {
            if ($node->extends) {
                $this->addDependency($node->extends);
            }
            if ($node->implements) {
                foreach ($node->implements as $implemented) {
                    $this->addDependency($implemented);
                }
            }
        } elseif ($node instanceof PHPParser_Node_Stmt_Interface) {
            foreach ($node->extends as $implemented) {
                $this->addDependency($implemented);
            }
        }

        if ($node instanceof PHPParser_Node_Param && $node->type instanceof PHPParser_Node_Name) {
            $this->addDependency($node->type);
        } elseif (
            $node instanceof PHPParser_Node_Expr_StaticCall
            || $node instanceof PHPParser_Node_Expr_StaticPropertyFetch
            || $node instanceof PHPParser_Node_Expr_New
        ) {
            if ($node->class instanceof PHPParser_Node_Name
                && !in_array((string) $node->class, array('parent', 'self'))
            ) {
                $this->addDependency($node->class);
            }
        } elseif ($node instanceof PHPParser_Node_Stmt_Catch) {
            $this->addDependency($node->type);
        } elseif (
            $node instanceof PHPParser_Node_Expr_ClassConstFetch
            || $node instanceof PHPParser_Node_Expr_Instanceof
        ) {
            if (!in_array((string) $node->class, array('self', 'static'))) {
                $this->addDependency($node->class);
            }
        }
    }

    public function leaveNode(PHPParser_Node $node)
    {
        if ($node instanceof PHPParser_Node_Stmt_Class || $node instanceof PHPParser_Node_Stmt_Interface) {
            $this->dependencies[$this->current] = array_unique($this->dependencies[$this->current]);
            $this->current = null;
        }
    }

    private function addDependency($dependency)
    {
        $this->dependencies[$this->current][] = (string) $dependency;
    }
}
