import React, {
  Dispatch,
  RefObject,
  SetStateAction,
  useImperativeHandle,
  useRef,
} from "react";
import {
  FilterShowFieldInterface,
  FilterTypeInterface,
} from "../../../interface/ListSageEntityInterface";
import { getTranslations } from "../../../functions/translations";
import { useSearchParams } from "react-router-dom";

let translations: any = getTranslations();

type State = {
  filterTypes: FilterTypeInterface;
  filterFields: FilterShowFieldInterface[];
  showFields: FilterShowFieldInterface[];
  hideFields: string[];
};

type State2 = {
  initFilter: FilterInput;
  filterTypes: FilterTypeInterface;
  filterFields: FilterShowFieldInterface[];
  removeFilter: (index: number) => void;
  index: number;
  applyFilters: () => void;
};

interface _FilterInput {
  field: SubFilterInput;
  type: SubFilterInput;
  value: SubFilterInput;
}

interface FilterInput extends _FilterInput {
  ref: RefObject<any>;
}

type State3 = {
  field: keyof _FilterInput;
  setFilter: Dispatch<SetStateAction<FilterInput>>;
  filter: FilterInput;
  filterTypes: FilterTypeInterface;
  filterFields: FilterShowFieldInterface[];
  applyFilters: () => void;
  index: number;
};

interface SubFilterOptionLabelInterface {
  label: string;
  values: string[];
}

interface SubFilterOptionInput {
  key: string;
  label: string | SubFilterOptionLabelInterface;
}

interface SubFilterInput {
  options?: SubFilterOptionInput[];
  value?: string;
}

export const FilterSubInputComponent = React.forwardRef(
  (
    {
      field,
      setFilter,
      filter,
      filterTypes,
      filterFields,
      applyFilters,
      index,
    }: State3,
    ref,
  ) => {
    const filterSub = filter[field];
    const onChange = (
      event:
        | React.ChangeEvent<HTMLSelectElement>
        | React.ChangeEvent<HTMLInputElement>,
    ) => {
      setFilter((x) => {
        return {
          ...x,
          [field]: {
            ...x[field],
            value: event.target.value,
          },
        };
      });
    };

    const onFilterChange = () => {
      const result = { ...filter };
      let hasChanged = false;
      const filterField = filterFields.find(
        (x) => x.name === result.field.value,
      );
      const filterType = filterTypes[filterField.type];
      const newOptions = filterType.map((option) => {
        return {
          key: option,
          label: translations.words[option],
        };
      });
      if (JSON.stringify(result.type.options) !== JSON.stringify(newOptions)) {
        hasChanged = true;
        result.type.options = newOptions;
        if (!newOptions.find((x) => x.key === result.type.value)) {
          result.type.value = newOptions[0].key;
        }
      }

      const newValues =
        Object.keys(filterField.values ?? []).map((i) => {
          return {
            key: i.toString(),
            label: "[" + i + "]: " + filterField.values[i],
          };
        }) ?? [];
      if (JSON.stringify(result.value.options) !== JSON.stringify(newValues)) {
        hasChanged = true;
        result.value.options = newValues;
        if (
          newValues.length > 0 &&
          !newValues.find((x) => x.key === result.value.value)
        ) {
          result.value.value = newValues[0].key;
        }
      }

      if (hasChanged) {
        setFilter(result);
      }
    };

    const name = `filter_${field}[${index}]`;
    useImperativeHandle(ref, () => ({
      getValue() {
        return {
          [name]: filterSub.value,
        };
      },
    }));

    React.useEffect(() => {
      onFilterChange();
      const timeoutTyping = setTimeout(
        () => {
          if (field === "value") {
            applyFilters();
          }
        },
        (filterSub?.options?.length ?? 0) > 0 ? 0 : 500,
      );
      return () => clearTimeout(timeoutTyping);
    }, [filter]);

    return (
      <div>
        <label className="screen-reader-text" htmlFor={name}>
          {name}
        </label>
        {filterSub?.options && filterSub?.options.length > 0 ? (
          <select name={name} value={filterSub.value} onChange={onChange}>
            <option value="" disabled={true}>
              {translations.words.selectOption}
            </option>
            {filterSub.options.map((option) => {
              let label = option.label;
              if (typeof label === "object") {
                label = label.label;
              }
              return (
                <option key={option.key} value={option.key}>
                  {label}
                </option>
              );
            })}
          </select>
        ) : (
          <input
            type="search"
            name={name}
            value={filterSub.value}
            onChange={onChange}
          />
        )}
      </div>
    );
  },
);

export const FilterInputComponent = React.forwardRef(
  (
    {
      initFilter,
      filterTypes,
      filterFields,
      removeFilter,
      index,
      applyFilters,
    }: State2,
    ref,
  ) => {
    const [filter, setFilter] = React.useState<FilterInput>(initFilter);
    const fieldRef = useRef<any>(null);
    const typeRef = useRef<any>(null);
    const valueRef = useRef<any>(null);

    useImperativeHandle(ref, () => ({
      getValue() {
        return {
          ...fieldRef.current.getValue(),
          ...typeRef.current.getValue(),
          ...valueRef.current.getValue(),
        };
      },
    }));

    return (
      <div
        style={{
          display: "flex",
          flexWrap: "wrap",
          marginBottom: "0.5rem",
        }}
      >
        <FilterSubInputComponent
          field={"field"}
          filterTypes={filterTypes}
          filter={filter}
          index={index}
          setFilter={setFilter}
          filterFields={filterFields}
          applyFilters={applyFilters}
          ref={fieldRef}
        />
        <FilterSubInputComponent
          field={"type"}
          filterTypes={filterTypes}
          filter={filter}
          index={index}
          setFilter={setFilter}
          filterFields={filterFields}
          applyFilters={applyFilters}
          ref={typeRef}
        />
        <FilterSubInputComponent
          field={"value"}
          filterTypes={filterTypes}
          filter={filter}
          index={index}
          setFilter={setFilter}
          filterFields={filterFields}
          applyFilters={applyFilters}
          ref={valueRef}
        />
        <span
          onClick={() => removeFilter(index)}
          className="dashicons dashicons-trash button"
          style={{
            paddingRight: "22px",
          }}
        ></span>
      </div>
    );
  },
);

export const ListSageEntityFilterComponent: React.FC<State> = ({
  filterTypes,
  filterFields,
  showFields,
  hideFields,
}) => {
  const [init, setInit] = React.useState<boolean>(false);
  const [filterCondition, setFilterCondition] = React.useState<string>("or");
  const [filters, setFilters] = React.useState<(FilterInput | undefined)[]>([]);
  const [searchParams, setSearchParams] = useSearchParams();
  const [optionsField] = React.useState<SubFilterOptionInput[]>(
    filterFields.map((f) => {
      return {
        key: f.name,
        label: translations[f.transDomain][f.name],
      };
    }),
  );

  const addFilter = () => {
    setFilters((x) => {
      return [
        ...x,
        {
          field: {
            options: optionsField,
            // @ts-ignore
            value: filterFields[Object.keys(filterFields)[0]].name,
          },
          type: {
            options: [],
            value: "",
          },
          value: {
            options: [],
            value: "",
          },
          ref: React.createRef(),
        },
      ];
    });
  };

  const removeFilter = (index: number) => {
    setFilters((x) => {
      const result = [...x];
      result[index] = undefined;
      return result;
    });
  };

  const initFilters = () => {
    const result: { [key: string]: FilterInput } = {};
    for (const [key, value] of searchParams) {
      if (!key.startsWith("filter_")) {
        continue;
      }
      const filterIndex = key.replace(/\D+/g, "");
      if (!result.hasOwnProperty(filterIndex)) {
        result[filterIndex] = {
          field: {
            options: optionsField,
            value: "",
          },
          type: {
            options: [],
            value: "",
          },
          value: {
            options: [],
            value: "",
          },
          ref: React.createRef(),
        };
      }
      const filterName = key.replace("filter_", "").replace(/\[\d+\]/, "");
      // @ts-ignore
      result[filterIndex][filterName].value = value;
    }
    setFilters(Object.values(result));
    setFilterCondition(searchParams.get("where_condition") ?? "or");
    setTimeout(() => {
      setInit(true);
    });
  };

  const applyFilters = () => {
    if (!init) {
      initFilters();
      return;
    }
    const result = filters
      .filter((x) => x)
      .map((x) => x.ref.current.getValue())
      .reduce((acc, curr) => ({ ...acc, ...curr }), {});
    const sort = searchParams.get("sort");
    setSearchParams(
      {
        ...result,
        page: searchParams.get("page"),
        paged: "1",
        ...(sort && {
          sort: sort,
        }),
        where_condition: filterCondition,
      },
      {
        replace: true,
      },
    );
  };

  React.useEffect(() => {
    applyFilters();
  }, [filters, filterCondition, init]);

  const hasMultipleFilters = filters.filter((x) => x).length > 1;
  return (
    <div className={"alignleft actions"}>
      <input
        style={{
          marginBottom: "0.5rem",
        }}
        type="button"
        className="button"
        value={translations.words.addFilter}
        onClick={addFilter}
      />
      <div style={{ display: "flex", alignItems: "center" }}>
        {hasMultipleFilters && (
          <div>
            <select
              value={filterCondition}
              name="where_condition"
              onChange={(e) => setFilterCondition(e.target.value)}
            >
              <option value="or">{translations.words.or}</option>
              <option value="and">{translations.words.and}</option>
            </select>
          </div>
        )}
        <div
          style={{
            ...(hasMultipleFilters && {
              borderLeft: "1px solid",
              paddingLeft: "1rem",
            }),
          }}
        >
          {filters.map((filter, index) => {
            return (
              <React.Fragment key={index}>
                {filter && (
                  <FilterInputComponent
                    initFilter={filter}
                    index={index}
                    filterTypes={filterTypes}
                    filterFields={filterFields}
                    removeFilter={removeFilter}
                    applyFilters={applyFilters}
                    ref={filter.ref}
                  />
                )}
              </React.Fragment>
            );
          })}
        </div>
      </div>
    </div>
  );
};
