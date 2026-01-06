// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import { createRoot } from "react-dom/client";
import React, { RefObject, useImperativeHandle } from "react";
import Box from "@mui/material/Box";
import { getTranslations } from "../../../../functions/translations";
import { InputInterface } from "../../../../interface/InputInterface";
import { Tooltip } from "@mui/material";
import { BrowserRouter, useSearchParams } from "react-router-dom";

let translations: any = getTranslations();

interface AllFilterTypeInterface {
  DateTimeOperationFilterInput: string[];
  DecimalOperationFilterInput: string[];
  IntOperationFilterInput: string[];
  ShortOperationFilterInput: string[];
  StringOperationFilterInput: string[];
  UuidOperationFilterInput: string[];
  BooleanOperationFilterInput: string[];
}

interface FilterFieldInterface {
  name: string;
  transDomain: string;
  type: keyof AllFilterTypeInterface;
  values: string[] | Record<number, string> | null;
}

export interface ResourceFilterDataInterface {
  allFilterType: AllFilterTypeInterface;
  filterFields: FilterFieldInterface[];
  importCondition: {
    field: string;
    value: (number | string)[] | string | number | null;
    condition: string;
  }[];
  initFilter?: FilterInterface;
}

interface FilterValueInterface {
  field: string;
  condition: string;
  value: (number | string)[] | string | number | null;
  deletable?: boolean;
  editable?: boolean;
  ref: RefObject<any>;
}

export interface FilterInterface {
  condition: "and" | "or";
  conditionValues?: "and" | "or";
  allowCondition?: boolean;
  allowConditionValues?: boolean;
  canAddValue?: boolean;
  values: FilterValueInterface[];
  subFilter?: FilterInterface;
  ref: RefObject<any>;
}

interface State {
  resourceFilter: ResourceFilterDataInterface;
  textInput?: HTMLInputElement;
  checkboxInput?: HTMLInputElement;
  withUrl?: boolean;
  allowEditImportCondition?: boolean;
  onDispatch?: () => void;
}

interface State2 {
  filter: FilterInterface;
  allFilterType: AllFilterTypeInterface;
  filterFields: FilterFieldInterface[];
  depth: number;
  dispatch: () => void;
  setParentValues?: React.Dispatch<React.SetStateAction<FormState>>;
}

interface State3 {
  allFilterType: AllFilterTypeInterface;
  filterFields: FilterFieldInterface[];
  filterValue: FilterValueInterface;
  removeFilterValue: (index: number) => void;
  index: number;
  dispatch: () => void;
}

interface FormState {
  condition: InputInterface;
  conditionValues: InputInterface;
  values: InputInterface;
  subFilter: InputInterface;
}

interface FormState2 {
  field: InputInterface;
  condition: InputInterface;
  value: InputInterface;
}

const FilterInputComponent = React.forwardRef(
  (
    {
      filterValue,
      filterFields,
      allFilterType,
      removeFilterValue,
      index,
      dispatch,
    }: State3,
    ref,
  ) => {
    const getFilterField = (key: string) => {
      return filterFields.find((f) => f.name === key) ?? filterFields[0];
    };
    const getFilterType = (key: keyof AllFilterTypeInterface) => {
      return {
        key: key,
        values: allFilterType[key],
      };
    };
    const [filterField, setFilterField] = React.useState<FilterFieldInterface>(
      getFilterField(filterValue.field),
    );
    const [filterType, setFilterType] = React.useState(
      getFilterType(filterField.type),
    );
    const [init, setInit] = React.useState(false);
    const getDefaultValue = (): FormState2 => {
      return {
        field: {
          value: filterField.name,
          error: "",
        },
        condition: {
          value: filterValue.condition,
          error: "",
        },
        value: {
          value: filterValue.value,
          error: "",
        },
      };
    };
    const [values, setValues] = React.useState<FormState2>(getDefaultValue());

    React.useEffect(() => {
      if (init && filterType.key === "DateTimeOperationFilterInput") {
        dispatch();
      }
    }, [values.condition.value]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      if (init) {
        dispatch();
      } else {
        setInit(true);
      }
    }, [values.value.value]); // eslint-disable-line react-hooks/exhaustive-deps

    useImperativeHandle(ref, () => ({
      getValue(): FilterValueInterface | undefined {
        if (
          !values.field.value ||
          !values.condition.value ||
          values.value.value === ""
        ) {
          return undefined;
        }
        const result: FilterValueInterface = {
          ...filterValue,
          field: values.field.value,
          condition: values.condition.value,
          value: values.value.value,
        };
        delete result.ref;
        return result;
      },
    }));

    const isMultiple = ["in", "nin"].includes(values.condition.value);
    return (
      <div
        style={{
          display: "flex",
          flexWrap: "wrap",
          marginBottom: "0.5rem",
          alignItems: "center",
        }}
      >
        <div>
          <select
            value={values.field.value}
            onChange={(e) => {
              setValues((v) => {
                v.field.value = e.target.value;
                const thisFilterField = getFilterField(e.target.value);
                setFilterField(thisFilterField);
                const thisFilterType = getFilterType(thisFilterField.type);
                if (thisFilterType.key !== filterType.key) {
                  setFilterType(thisFilterType);
                  v.condition.value = thisFilterType.values[0];
                  v.value.value = "";
                }
                return { ...v };
              });
            }}
          >
            <option value="" disabled={true}>
              {translations.words.selectOption}
            </option>
            {filterFields.map((option) => {
              let label = translations[option.transDomain][option.name];
              if (typeof label === "object") {
                label = label.label;
              }
              return (
                <option
                  disabled={
                    filterValue.editable === false &&
                    option.name !== values.field.value
                  }
                  key={option.name}
                  value={option.name}
                >
                  {label}
                </option>
              );
            })}
          </select>
        </div>
        <div>
          <select
            value={values.condition.value}
            onChange={(e) => {
              setValues((v) => {
                v.condition.value = e.target.value;
                if (filterType.key !== "DateTimeOperationFilterInput") {
                  v.value.value = "";
                }
                return { ...v };
              });
            }}
          >
            <option value="" disabled={true}>
              {translations.words.selectOption}
            </option>
            {(filterType.values ?? []).map((option) => {
              return (
                <option
                  key={option}
                  value={option}
                  disabled={
                    filterValue.editable === false &&
                    option !== values.condition.value
                  }
                >
                  {translations.words[option]}
                </option>
              );
            })}
          </select>
        </div>
        <div>
          {filterField.values ? (
            <Tooltip
              title={
                isMultiple
                  ? translations.sentences.maintainCtrlSelectMultiple
                  : ""
              }
              arrow
              placement="top"
            >
              <select
                value={values.value.value}
                multiple={isMultiple}
                className={isMultiple ? "resize-select" : ""}
                onChange={(e) => {
                  if (filterValue.editable === false) {
                    return;
                  }
                  const options = e.target.options;
                  const selected: number[] = [];
                  for (let i = 0; i < options.length; i++) {
                    if (options[i].selected) {
                      selected.push(Number(options[i].value));
                    }
                  }
                  setValues((v) => {
                    if (isMultiple) {
                      v.value.value = selected;
                    } else {
                      v.value.value = selected[0];
                    }
                    return { ...v };
                  });
                }}
              >
                <option value="" disabled={true}>
                  {translations.words.selectOption}
                </option>
                {(Array.isArray(filterField.values)
                  ? filterField.values.map((v, i) => [i, v] as const)
                  : Object.entries(filterField.values)
                ).map(([keyOption, option]) => {
                  let disabled = filterValue.editable === false;
                  if (isMultiple) {
                    disabled =
                      disabled && !values.value.value.includes(keyOption);
                  } else {
                    disabled = disabled && keyOption !== values.value.value;
                  }
                  return (
                    <option
                      key={keyOption}
                      value={keyOption}
                      disabled={disabled}
                    >
                      {`[${keyOption}] ${option}`}
                    </option>
                  );
                })}
              </select>
            </Tooltip>
          ) : filterType.key === "BooleanOperationFilterInput" ? (
            <>
              <select
                value={
                  values.value.value
                    ? "1"
                    : values.value.value === false
                      ? "0"
                      : ""
                }
                onChange={(e) => {
                  setValues((v) => {
                    if (filterValue.editable === false) {
                      return v;
                    }
                    v.value.value = e.target.value === "1";
                    return { ...v };
                  });
                }}
              >
                <option value="" disabled={true}>
                  {translations.words.selectOption}
                </option>
                <option
                  value={"1"}
                  disabled={
                    filterValue.editable === false &&
                    (values.value.value ? 1 : 0) !== 1
                  }
                >
                  {translations.words.yes}
                </option>
                <option
                  value={"0"}
                  disabled={
                    filterValue.editable === false &&
                    (values.value.value ? 1 : 0) !== 0
                  }
                >
                  {translations.words.no}
                </option>
              </select>
            </>
          ) : (
            <input
              value={values.value.value}
              type={
                filterType.key === "DateTimeOperationFilterInput"
                  ? "date"
                  : "text"
              }
              readOnly={filterValue.editable === false}
              onChange={(e) => {
                setValues((v) => {
                  if (filterValue.editable === false) {
                    return v;
                  }
                  v.value.value = e.target.value;
                  return { ...v };
                });
              }}
            />
          )}
        </div>
        {filterValue.deletable !== false && (
          <div style={{ display: "flex", alignItems: "center" }}>
            <Tooltip
              title={translations.words.removeFilter}
              arrow
              placement="top"
            >
              <span
                onClick={() => {
                  if (filterValue.deletable === false) {
                    return;
                  }
                  removeFilterValue(index);
                }}
                className="dashicons dashicons-trash button"
                style={{
                  paddingRight: "22px",
                  marginLeft: "1rem",
                }}
              ></span>
            </Tooltip>
          </div>
        )}
      </div>
    );
  },
);

const _ResourceFilterComponent = React.forwardRef(
  (
    {
      filter,
      filterFields,
      allFilterType,
      depth,
      dispatch,
      setParentValues,
    }: State2,
    ref,
  ) => {
    const getDefaultValue = (): FormState => {
      const availableFields = filterFields.map(f => f.name);
      return {
        condition: {
          value: filter.condition,
          error: "",
        },
        conditionValues: {
          value: filter.conditionValues ?? filter.condition,
          error: "",
        },
        values: {
          value: [
            ...(filter.values
              ?.filter((filterValue) =>
                availableFields.includes(filterValue.field),
              )
              ?.map((filterValue) => {
                return {
                  ...filterValue,
                  ref: React.createRef(),
                };
              }) ?? []),
          ],
          error: "",
        },
        subFilter: {
          value: filter.subFilter
            ? { ...filter.subFilter, ref: React.createRef() }
            : null,
          error: "",
        },
      };
    };
    const [values, setValues] = React.useState<FormState>(getDefaultValue());
    const [oldSubFilter, setOldSubFilter] = React.useState<FormState>(
      values.subFilter.value,
    );
    const [oldNbNoValues, setOldNbNoValues] = React.useState<FormState>(
      values.values.value.filter((x: FilterValueInterface) => !x).length,
    );
    const [initCondition, setInitCondition] = React.useState(false);
    const [initConditionValues, setInitConditionValues] = React.useState(false);

    const removeFilterValue = (index: number) => {
      if (
        setParentValues &&
        values.values.value.filter((x: FilterValueInterface) => x !== undefined)
          .length === 1
      ) {
        setParentValues((v) => {
          v.subFilter.value = undefined;
          return { ...v };
        });
      } else {
        setValues((v) => {
          v.values.value[index] = undefined;
          return { ...v };
        });
      }
    };

    const getNewFilterValue = (): FilterValueInterface => {
      return {
        field: "",
        condition: "",
        value: "",
        ref: React.createRef(),
      };
    };

    const addFilterValue = () => {
      setValues((v) => {
        v.values.value.push(getNewFilterValue());
        return { ...v };
      });
    };

    const addFilter = () => {
      setValues((v) => {
        v.subFilter.value = {
          condition: "and",
          values: [getNewFilterValue()],
          ref: React.createRef(),
        } as FilterInterface;
        return { ...v };
      });
    };

    React.useEffect(() => {
      setValues(getDefaultValue());
    }, [filter]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      if (oldSubFilter && !values.subFilter.value) {
        dispatch();
      }
      setOldSubFilter(values.subFilter.value);
    }, [values.subFilter.value]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      const newNbNoValues = values.values.value.filter(
        (x: FilterValueInterface) => !x,
      ).length;
      if (newNbNoValues > oldNbNoValues) {
        dispatch();
        setOldNbNoValues(newNbNoValues);
      }
    }, [values.values.value.filter((x: FilterValueInterface) => !x).length]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      if (initCondition) {
        dispatch();
      } else {
        setInitCondition(true);
      }
    }, [values.condition.value]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      if (initConditionValues) {
        dispatch();
      } else {
        setInitConditionValues(true);
      }
    }, [values.conditionValues.value]); // eslint-disable-line react-hooks/exhaustive-deps

    useImperativeHandle(ref, () => ({
      getValue(): FilterInterface {
        const filterValues: FilterValueInterface[] = values.values.value
          .filter((filterValue: FilterValueInterface) => filterValue)
          .map((filterValue: FilterValueInterface) => {
            return filterValue.ref.current.getValue();
          })
          .filter((x: any) => x);
        if (filterValues.length === 0) {
          return undefined;
        }
        const result: FilterInterface = {
          ...filter,
          condition: values.condition.value,
          conditionValues: values.conditionValues.value,
          values: filterValues,
          subFilter: undefined,
        };
        if (values.subFilter.value) {
          result.subFilter = values.subFilter.value.ref.current.getValue();
        }
        delete result.ref;
        return result;
      },
    }));

    return (
      <>
        <div
          style={{ display: "flex", alignItems: "stretch", marginTop: "1rem" }}
        >
          {values.subFilter.value && (
            <div
              style={{
                borderRight: "1px solid",
                paddingRight: "1rem",
                marginRight: "1rem",
                display: "flex",
                alignItems: "center",
              }}
            >
              <select
                value={values.condition.value}
                onChange={(e) => {
                  setValues((v) => {
                    v.condition.value = e.target.value;
                    return { ...v };
                  });
                }}
              >
                {["or", "and"].map((c, cIndex) => (
                  <option
                    key={cIndex}
                    disabled={
                      filter.allowCondition === false &&
                      values.condition.value !== c
                    }
                    value={c}
                  >
                    {translations.words[c]}
                  </option>
                ))}
              </select>
            </div>
          )}
          <div>
            <div style={{ display: "flex" }}>
              <div>
                {values.values.value.map(
                  (
                    filterValue: FilterValueInterface | undefined,
                    indexValue: number,
                  ) => (
                    <React.Fragment key={indexValue}>
                      {filterValue && (
                        <FilterInputComponent
                          filterValue={filterValue}
                          ref={filterValue.ref}
                          allFilterType={allFilterType}
                          filterFields={filterFields}
                          index={indexValue}
                          removeFilterValue={removeFilterValue}
                          dispatch={dispatch}
                        />
                      )}
                    </React.Fragment>
                  ),
                )}
                <div style={{ textAlign: "center", marginBottom: "0.5rem" }}>
                  {values.values.value.filter(
                    (filterValue: FilterValueInterface) => filterValue,
                  ).length > 0 ? (
                    <Tooltip
                      title={translations.words.addFilter}
                      arrow
                      placement="top"
                    >
                      <span
                        onClick={addFilterValue}
                        className="dashicons dashicons-plus button"
                        style={{
                          paddingRight: "22px",
                          marginLeft: "1rem",
                        }}
                      ></span>
                    </Tooltip>
                  ) : (
                    <button className="button-primary" onClick={addFilterValue}>
                      {translations.words.addFilter}
                    </button>
                  )}
                  {!values.subFilter.value &&
                    values.values.value.filter(
                      (filterValue: FilterValueInterface) => filterValue,
                    ).length > 0 && (
                      <Tooltip
                        title={translations.words.addSubFilter}
                        arrow
                        placement="top"
                      >
                        <span
                          onClick={addFilter}
                          className="dashicons dashicons-welcome-add-page button"
                          style={{
                            paddingRight: "22px",
                            marginLeft: "1rem",
                          }}
                        ></span>
                      </Tooltip>
                    )}
                </div>
              </div>
              {values.values.value &&
                values.values.value.filter(
                  (value: FilterValueInterface | undefined) => value,
                ).length > 1 && (
                  <div
                    style={{
                      borderLeft: "1px solid",
                      paddingLeft: "1rem",
                      marginLeft: "1rem",
                      display: "flex",
                      alignItems: "center",
                    }}
                  >
                    <select
                      value={values.conditionValues.value}
                      onChange={(e) => {
                        setValues((v) => {
                          v.conditionValues.value = e.target.value;
                          return { ...v };
                        });
                      }}
                    >
                      {["or", "and"].map((c, cIndex) => (
                        <option
                          key={cIndex}
                          disabled={
                            filter.allowConditionValues === false &&
                            values.conditionValues.value !== c
                          }
                          value={c}
                        >
                          {translations.words[c]}
                        </option>
                      ))}
                    </select>
                  </div>
                )}
            </div>
            {values.subFilter.value && (
              <_ResourceFilterComponent
                ref={values.subFilter.value.ref}
                filter={values.subFilter.value}
                allFilterType={allFilterType}
                depth={depth + 1}
                filterFields={filterFields}
                dispatch={dispatch}
                setParentValues={setValues}
              />
            )}
          </div>
        </div>
      </>
    );
  },
);

export const ResourceFilterComponent: React.FC<State> = ({
  resourceFilter,
  textInput,
  checkboxInput,
  withUrl,
  allowEditImportCondition,
  onDispatch,
}) => {
  const [searchParams, setSearchParams] = useSearchParams();
  const [show, setShow] = React.useState<boolean>(
    checkboxInput?.checked ?? true,
  );
  const getDefaultFilter = () => {
    if (!show) {
      return null;
    }
    const refFilterForm = React.createRef();
    let newFilter: FilterInterface = {
      condition: "and",
      allowCondition: !!allowEditImportCondition,
      allowConditionValues: !!allowEditImportCondition,
      values: resourceFilter.importCondition.map((importCondition) => {
        return {
          field: importCondition.field,
          value: importCondition.value,
          condition: importCondition.condition,
          deletable: !!allowEditImportCondition,
          editable: !!allowEditImportCondition,
          ref: React.createRef(),
        };
      }),
      ref: refFilterForm,
    };
    let initFilter = resourceFilter.initFilter;
    if (withUrl) {
      const filterParam = searchParams.get("filter");
      if (filterParam) {
        try {
          newFilter = JSON.parse(decodeURIComponent(filterParam));
          newFilter.ref = refFilterForm;
          initFilter = undefined;
        } catch (e) {
          // console.error(e);
        }
      }
    }
    if (textInput) {
      try {
        initFilter = JSON.parse($(textInput).attr("data-init-filter"));
      } catch (e) {
        // console.error(e);
      }
    }
    if (initFilter) {
      for (const oldValue of initFilter.values) {
        const newValue = newFilter.values.find(
          (v) =>
            v.field === oldValue.field &&
            v.condition === oldValue.condition &&
            JSON.stringify(v.value) === JSON.stringify(oldValue.value),
        );
        if (!newValue) {
          newFilter.values.push({
            field: oldValue.field,
            value: oldValue.value,
            condition: oldValue.condition,
            ref: React.createRef(),
          });
        }
      }
      if (initFilter.subFilter) {
        newFilter.subFilter = initFilter.subFilter;
      }
    }
    return newFilter;
  };
  const [filter, setFilter] = React.useState<FilterInterface | null>(null);
  const [filterUrl, setFilterUrl] = React.useState<FilterInterface | null>(
    null,
  );

  const dispatch = () => {
    const newFilter: FilterInterface = filter.ref.current.getValue();
    if (textInput) {
      textInput.value = JSON.stringify(newFilter);
    }
    if (withUrl) {
      setFilterUrl(newFilter);
    }
  };

  React.useEffect(() => {
    const handleChange = (event: Event) => {
      const newShow = (event.target as HTMLInputElement).checked;
      setShow(newShow);
      if (!newShow) {
        textInput.value = "";
      }
    };
    checkboxInput?.addEventListener("change", handleChange);
    return () => {
      checkboxInput?.removeEventListener("change", handleChange);
    };
  }, []);

  React.useEffect(() => {
    setFilter(getDefaultFilter());
  }, [resourceFilter, show]);

  React.useEffect(() => {
    if (filter?.ref) {
      dispatch();
    }
  }, [filter?.ref]);

  React.useEffect(() => {
    const timeoutTyping = setTimeout(() => {
      setSearchParams((x) => {
        const params = new URLSearchParams(x);
        if (filterUrl) {
          params.set("filter", encodeURIComponent(JSON.stringify(filterUrl)));
        } else {
          params.delete("filter");
        }
        if (onDispatch) {
          setTimeout(() => {
            onDispatch();
          }, 1);
        }
        return params;
      });
    }, 500);
    return () => clearTimeout(timeoutTyping);
  }, [filterUrl]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <>
      {show && filter && (
        <Box sx={{ width: "100%" }}>
          <_ResourceFilterComponent
            ref={filter.ref}
            filter={filter}
            allFilterType={resourceFilter.allFilterType}
            filterFields={resourceFilter.filterFields}
            depth={0}
            dispatch={dispatch}
          />
        </Box>
      )}
    </>
  );
};

document.querySelectorAll("[data-resource-filter]").forEach((dom) => {
  const root = createRoot(dom.querySelector("[data-react-resource]"));
  root.render(
    <BrowserRouter>
      <ResourceFilterComponent
        resourceFilter={JSON.parse($(dom).attr("data-resource-filter"))}
        textInput={dom.querySelector("input[type='text']")}
        checkboxInput={dom.querySelector<HTMLInputElement>(
          "input[type='checkbox']",
        )}
      />
    </BrowserRouter>,
  );
});
